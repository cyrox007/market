<?php

namespace App\Services\Catalog\Integrations;

use App\Models\CatalogImportRun;
use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use App\Models\Product\Manufacturer;
use App\Models\Product\Product;
use App\Services\Catalog\AbstractCatalogImport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Vanilo\Category\Models\Taxonomy;

class Svetofor1CCatalogImport extends AbstractCatalogImport
{
    protected const DEFAULT_BASE_URL = 'http://api.svetofor-mebel.ru';

    protected string $baseUrl;

    protected string $apiKey;

    protected int $timeout;

    /** Путь для загрузки остатков (syncStockItemsBulkFrom1C). Пусто = не вызывать */
    protected string $stockPath;

    protected string $productsCachePath;

    /** @var array<int, int> external_id => our category id */
    protected array $categoryIdMap = [];

    /** @var array<string, int> manufacturer externalId (from API) => our manufacturer id */
    protected array $manufacturerIdByExternalId = [];

    /** @var array<int, string> API manufacturer id => externalId (для маппинга из товара по manufacturerId) */
    protected array $manufacturerIdToExternalId = [];

    protected int $taxonomyId;

    public function __construct()
    {
        $key = static::getConfigKey();
        $cfg = $key !== null ? config("catalog_import.config.{$key}", []) : [];
        $baseUrl = (string) ($cfg['base_url'] ?? '');
        $this->baseUrl = rtrim($baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL, '/');
        $this->apiKey = (string) ($cfg['api_key'] ?? '');
        $this->timeout = (int) ($cfg['timeout'] ?? 60);
        $this->stockPath = ltrim((string) ($cfg['stock_path'] ?? ''), '/');
        $this->productsCachePath = ltrim((string) ($cfg['products_cache_path'] ?? '/api/v1/integration/1c/v2/cache/products'), '/');
        $this->taxonomyId = $this->getTaxonomyId();
    }

    public static function getLabel(): string
    {
        return 'Светофор 1C';
    }

    public static function getConfigKey(): ?string
    {
        return 'svetofor_1c';
    }

    /**
     * Импортировать измененные товары по timestamp и вернуть их external_id.
     *
     * @return array{external_ids: array<int, string>, created: int, updated: int}
     */
    public function importChangedProducts(?string $updatedAfter = null): array
    {
        $raw = $this->fetchProductsRaw($updatedAfter);
        $mapped = $this->mapProducts($raw);
        $created = 0;
        $updated = 0;
        $rowsForFullCreate = [];

        $externalIds = [];
        foreach ($mapped as $row) {
            $externalId = (string) ($row['external_id'] ?? '');
            if ($externalId !== '') {
                $externalIds[] = $externalId;
            }

            $existing = $this->resolveExistingProductForMinuteSync($row);

            if ($existing === null) {
                $rowsForFullCreate[] = $row;
                continue;
            }

            $this->applyOperationalUpdateForMinuteSync($existing, $row);
            $updated++;
        }

        if ($rowsForFullCreate !== []) {
            [$createdFromCreateFlow, $updatedFromCreateFlow] = $this->persistProducts($rowsForFullCreate);
            $created += $createdFromCreateFlow;
            $updated += $updatedFromCreateFlow;
        }

        $externalIds = array_values(array_unique($externalIds));

        Log::info('Catalog import: importChangedProducts completed', [
            'updated_after' => $updatedAfter,
            'created' => $created,
            'updated' => $updated,
            'external_ids_count' => count($externalIds),
        ]);

        return [
            'external_ids' => $externalIds,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Для минутного инкрементального синка обновляем только операционные поля.
     * Контентные поля (name/slug/description и пр.) у существующих товаров не трогаем.
     *
     * @param  array<string, mixed>  $row
     */
    protected function applyOperationalUpdateForMinuteSync(Product $product, array $row): void
    {
        $incomingExternalId = (string) ($row['external_id'] ?? '');
        if ($incomingExternalId !== '' && ($product->external_id === null || $product->external_id === '')) {
            $product->external_id = $incomingExternalId;
            $product->saveQuietly();
            Log::info('Catalog import: minute sync backfilled product external_id by SKU match', [
                'product_id' => $product->id,
                'external_id' => $incomingExternalId,
                'sku' => $product->sku,
            ]);
        } elseif (
            $incomingExternalId !== ''
            && $product->external_id !== null
            && $product->external_id !== ''
            && $product->external_id !== $incomingExternalId
        ) {
            Log::warning('Catalog import: minute sync external_id mismatch for existing product', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'current_external_id' => $product->external_id,
                'incoming_external_id' => $incomingExternalId,
            ]);
        }

        $payloadKeys = isset($row['_payload_keys']) && is_array($row['_payload_keys']) ? $row['_payload_keys'] : [];
        $hasPrice = in_array('price', $payloadKeys, true) || in_array('basePrice', $payloadKeys, true);

        if ($hasPrice && array_key_exists('price', $row) && $row['price'] !== null) {
            $product->price = (float) $row['price'];
            $product->saveQuietly();

            Log::info('Catalog import: minute sync updated existing product price', [
                'product_id' => $product->id,
                'external_id' => $product->external_id,
                'price' => $product->price,
            ]);
            return;
        }

        Log::debug('Catalog import: minute sync skipped product price update', [
            'product_id' => $product->id,
            'external_id' => $product->external_id,
            'reason' => 'price_absent_in_payload',
        ]);
    }

    /**
     * Найти существующий родительский товар для минутного инкрементального sync.
     *
     * @param  array<string, mixed>  $row
     */
    protected function resolveExistingProductForMinuteSync(array $row): ?Product
    {
        $externalId = (string) ($row['external_id'] ?? '');
        if ($externalId !== '') {
            $byExternalId = Product::query()
                ->whereNull('parent_product_id')
                ->where('external_id', $externalId)
                ->first();
            if ($byExternalId !== null) {
                return $byExternalId;
            }
        }

        $sku = (string) ($row['sku'] ?? '');
        if ($sku !== '') {
            return Product::query()
                ->whereNull('parent_product_id')
                ->where('sku', $sku)
                ->first();
        }

        return null;
    }

    /**
     * Синхронизировать остатки по одному external_id для уже существующего товара/вариации.
     */
    public function syncStocksByExternalId(string $externalId): bool
    {
        $product = Product::query()->where('external_id', $externalId)->first();
        if ($product === null) {
            Log::warning('Catalog import: product not found for stock sync', [
                'external_id' => $externalId,
            ]);

            return false;
        }

        $result = $this->syncWarehouseStocksForProduct($product);

        return $result['synced'] !== [];
    }

    /**
     * Синхронизировать цену по external_id для уже существующего товара/вариации.
     */
    public function syncPriceByExternalId(string $externalId): bool
    {
        $product = Product::query()->where('external_id', $externalId)->first();
        if ($product === null) {
            Log::warning('Catalog import: product not found for price sync', [
                'external_id' => $externalId,
            ]);

            return false;
        }

        return $this->syncProductPriceFromCache($product, $externalId);
    }

    /**
     * Синхронизировать остатки по складам для товара и всех вариаций с заполненным external_id.
     *
     * @return array{synced: list<array{id: int, external_id: string, warehouse_rows: int}>, skipped: list<array{id: int, sku: string, reason: string}>}
     */
    public function syncWarehouseStocksForProduct(Product $product): array
    {
        $synced = [];
        $skipped = [];

        $targets = collect([$product]);
        if ($product->isVariable() && ! $product->isVariant()) {
            $targets = $targets->merge($product->variants()->get());
        }

        foreach ($targets as $target) {
            $externalId = trim((string) ($target->external_id ?? ''));
            if ($externalId === '') {
                $skipped[] = [
                    'id' => (int) $target->id,
                    'sku' => (string) ($target->sku ?? ''),
                    'reason' => 'Не указан внешний ID 1С (external_id)',
                ];

                continue;
            }

            $this->syncProductWarehouseStocks($target, $externalId);
            $warehouseRows = $target->fresh()->warehouseStocks()->count();

            $synced[] = [
                'id' => (int) $target->id,
                'external_id' => $externalId,
                'warehouse_rows' => $warehouseRows,
            ];
        }

        Log::info('Catalog import: warehouse stocks sync for product tree', [
            'root_product_id' => $product->id,
            'synced_count' => count($synced),
            'skipped_count' => count($skipped),
        ]);

        return ['synced' => $synced, 'skipped' => $skipped];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchCategoriesRaw(): array
    {
        // Импорт категорий отключён — импортируются только товары
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchProductsRaw(?string $updatedAfter = null): array
    {
        $url = $this->baseUrl . '/' . $this->productsCachePath;
        $resolvedUpdatedAfter = $updatedAfter !== null && $updatedAfter !== ''
            ? $updatedAfter
            : $this->resolveUpdatedAfterIso8601();
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->requestHeaders())
            ->get($url, ['updatedAfter' => $resolvedUpdatedAfter]);

        if (! $response->successful()) {
            throw new \RuntimeException('API products request failed: ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [];
        }
        $items = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        Log::info('Catalog import: products cache fetched', [
            'url' => $url,
            'updated_after' => $resolvedUpdatedAfter,
            'items_count' => count($items),
        ]);

        return array_values($items);
    }

    protected function resolveUpdatedAfterIso8601(): string
    {
        $key = static::getConfigKey();
        $cfg = $key !== null ? config("catalog_import.config.{$key}", []) : [];
        $initialUpdatedAfter = (string) ($cfg['initial_updated_after'] ?? '2000-01-01T00:00:00.000Z');
        $forceFullWhenEmpty = (bool) ($cfg['force_full_sync_when_catalog_empty'] ?? true);
        $isCatalogEmpty = Product::query()->whereNull('parent_product_id')->doesntExist();

        if ($forceFullWhenEmpty && $isCatalogEmpty) {
            Log::warning('Catalog import: empty catalog detected, forcing bootstrap updatedAfter', [
                'updated_after' => $initialUpdatedAfter,
            ]);

            return $initialUpdatedAfter;
        }

        $lastSuccess = CatalogImportRun::query()
            ->where('importer_class', static::class)
            ->where('status', CatalogImportRun::STATUS_SUCCESS)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->first();

        // После первого успешного импорта всегда работаем инкрементально от last finished_at.
        if ($lastSuccess?->finished_at !== null) {
            return $lastSuccess->finished_at->utc()->format('Y-m-d\TH:i:s.v\Z');
        }

        Log::info('Catalog import: first run bootstrap timestamp is used', [
            'updated_after' => $initialUpdatedAfter,
        ]);

        return $initialUpdatedAfter;
    }

    /**
     * Загрузить детали одного товара по external_id (описание, базовая цена, manufacturerId и т.д.).
     * Эндпоинт: GET /api/v1/integration/1c/products/external/{externalId}
     *
     * @return array<string, mixed>|null
     */
    protected function fetchProductDetailByExternalId(string $externalId): ?array
    {
        $url = $this->baseUrl . '/api/v1/integration/1c/products/external/' . $externalId;
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->requestHeaders())
            ->get($url);

        if (! $response->successful()) {
            Log::debug('Catalog import: product detail fetch failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);

            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Дополнить строку импорта данными из эндпоинта деталей товара (описание, базовая цена, manufacturerId).
     * manufacturerId в ответе API — внутренний id их системы; привязка к нашему производителю делается в syncProductManufacturer по этому id.
     *
     * @param  array<string, mixed>  $row
     */
    protected function enrichRowFromProductDetail(array &$row): void
    {
        $externalId = (string) $row['external_id'];
        $detail = $this->fetchProductDetailByExternalId($externalId);
        if ($detail === null) {
            return;
        }

        if (isset($detail['description']) && $detail['description'] !== null && $detail['description'] !== '') {
            $row['description'] = $this->decodeHtml((string) $detail['description']);
        }
        if (array_key_exists('basePrice', $detail) && $detail['basePrice'] !== null && $detail['basePrice'] !== '') {
            $row['price'] = (float) $detail['basePrice'];
        }
        if (isset($detail['manufacturerId']) && $detail['manufacturerId'] !== null) {
            $row['manufacturer_id'] = (int) $detail['manufacturerId'];
            // Сбрасываем имя из списка, чтобы имя бралось из справочника по id — у каждого товара свой производитель
            unset($row['manufacturer_name']);
        }
    }

    /**
     * Загрузить список производителей из API (для атрибута «производитель»).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchManufacturersRaw(): array
    {
        $url = $this->baseUrl . '/api/v1/integration/1c/manufacturers';
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->requestHeaders())
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('API manufacturers request failed: ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [];
        }
        $items = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        return array_values($items);
    }

    /**
     * Загрузить производителей из API (для подстановки имени в атрибут «Производитель», если в товаре передан только id).
     */
    protected function ensureManufacturersLoaded(): void
    {
        if ($this->manufacturerIdByExternalId !== []) {
            return;
        }

        try {
            $raw = $this->fetchManufacturersRaw();
        } catch (\Throwable $e) {
            Log::warning('Catalog import: manufacturers fetch failed', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl . '/api/v1/integration/1c/manufacturers',
            ]);

            return;
        }

        foreach ($raw as $item) {
            $name = $this->decodeHtml((string) ($item['name'] ?? '')) ?: (string) ($item['id'] ?? '');
            // Нормализуем slug из API (могут быть точки, например "futuka-kids.ru") для безопасного хранения в БД
            $slug = Str::slug((string) ($item['slug'] ?? $name));
            if ($slug === '') {
                $slug = 'm-' . ($item['id'] ?? 'unknown');
            }
            $externalId = $item['externalId'] ?? $item['external_id'] ?? null;
            $apiId = $item['id'] ?? null;

            if ($apiId !== null) {
                $this->manufacturerIdToExternalId[$apiId] = ($externalId !== null && $externalId !== '')
                    ? (string) $externalId
                    : 'api_' . $apiId;
            }

            $manufacturer = Manufacturer::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'external_id' => ($externalId !== null && $externalId !== '') ? (string) $externalId : null,
                ]
            );

            $key = ($externalId !== null && $externalId !== '') ? (string) $externalId : 'api_' . $apiId;
            $this->manufacturerIdByExternalId[$key] = (int) $manufacturer->id;
        }
    }

    /**
     * Внешний идентификатор 1С (поле externalId из API). Переопределите в наследнике под формат API.
     *
     * @param  array<string, mixed>  $item  сырой элемент категории из API
     * @return string|null
     */
    protected function getCategoryExternalId(array $item): ?string
    {
        $value = $item['externalId'] ?? $item['external_id'] ?? null;

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * Внешний идентификатор 1С (поле externalId из API). Переопределите в наследнике под формат API.
     *
     * @param  array<string, mixed>  $item  сырой элемент товара из API
     * @return string|null
     */
    protected function getProductExternalId(array $item): ?string
    {
        $value = $item['externalId'] ?? $item['external_id'] ?? null;

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * Декодирует HTML-сущности в строке (&quot; → ", &amp; → & и т.д.).
     */
    protected function decodeHtml(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    protected function mapCategories(array $raw): array
    {
        $mapped = [];
        foreach ($raw as $item) {
            if (empty($item['name'] ?? null) && empty($item['title'] ?? null)) {
                continue;
            }
            $name = $this->decodeHtml((string) ($item['name'] ?? $item['title'] ?? '')) ?? '';
            $desc = $item['description'] ?? null;
            $mapped[] = [
                'name' => $name,
                'slug' => $this->slugFrom($item['slug'] ?? null, $name),
                'parent_external_id' => $item['parentId'] ?? $item['parent_id'] ?? null,
                'priority' => (int) ($item['priority'] ?? $item['sort'] ?? 0),
                'description' => $desc !== null ? $this->decodeHtml((string) $desc) : null,
                'external_id' => $this->getCategoryExternalId($item),
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    protected function mapProducts(array $raw): array
    {
        $mapped = [];
        $skippedNoName = 0;
        $skippedNoExternalId = 0;
        foreach ($raw as $item) {
            // cache API возвращает envelope: { externalId, payload: {...} }
            $payload = (isset($item['payload']) && is_array($item['payload'])) ? $item['payload'] : $item;
            if (empty($payload['name'] ?? null) && empty($payload['title'] ?? null)) {
                $skippedNoName++;
                continue;
            }
            if ($this->getProductExternalId($item) === null) {
                $skippedNoExternalId++;
                continue;
            }
            $name = $this->decodeHtml((string) ($payload['name'] ?? $payload['title'] ?? '')) ?? '';
            $cleanName = $name;
            $unknow_code = null;

            // Ищем код в любом месте строки (не только в начале)
            if (preg_match('/\b(\d{3}\.\d{3}\.\d{3})\b/', $name, $matches)) {
                $unknow_code = $matches[1];
                // Удаляем этот код из названия вместе с пробелом после или перед
                $cleanName = trim(preg_replace('/\b' . preg_quote($unknow_code, '/') . '\s*/', '', $name));
            }

            $sku = $payload['sku']
                ?? $payload['article']
                ?? $payload['code']
                ?? $this->getProductExternalId($item)
                ?? Str::slug($name);
            $price = (float) ($payload['basePrice'] ?? $payload['price'] ?? $payload['price_old'] ?? 0);
            $desc = $payload['description'] ?? null;
            $excerpt = $payload['excerpt'] ?? $payload['shortDescription'] ?? null;
            $manufacturerExternalId = $payload['manufacturerExternalId'] ?? $payload['manufacturer_external_id'] ?? null;
            if ($manufacturerExternalId === null && isset($payload['manufacturer']['externalId'])) {
                $manufacturerExternalId = $payload['manufacturer']['externalId'];
            }
            $manufacturerExternalId = ($manufacturerExternalId !== null && $manufacturerExternalId !== '') ? (string) $manufacturerExternalId : null;
            $manufacturerName = $this->decodeHtml((string) ($payload['manufacturer']['name'] ?? $payload['manufacturerName'] ?? $payload['manufacturer'] ?? '')) ?: null;

            $mapped[] = [
                'name' => $cleanName,
                'sku' => (string) $sku,
                'slug' => $this->slugFrom($payload['slug'] ?? null, $name),
                'price' => $price,
                'original_price' => isset($payload['originalPrice']) ? (float) $payload['originalPrice'] : null,
                'description' => $desc !== null ? $this->decodeHtml((string) $desc) : null,
                'excerpt' => $excerpt !== null ? $this->decodeHtml((string) $excerpt) : null,
                'category_external_ids' => $this->normalizeCategoryIds($payload),
                'external_id' => $this->getProductExternalId($item),
                'manufacturer_external_id' => $manufacturerExternalId,
                'manufacturer_id' => isset($payload['manufacturerId']) ? (int) $payload['manufacturerId'] : null,
                'manufacturer_name' => $manufacturerName,
                // Полезно для выборочного обновления существующих товаров:
                // фиксируем какие поля реально были в payload.
                '_payload_keys' => array_keys($payload),
                'other_code' => $unknow_code
            ];
        }

        Log::info('Catalog import: mapProducts summary', [
            'raw_count' => count($raw),
            'mapped_count' => count($mapped),
            'skipped_no_name' => $skippedNoName,
            'skipped_no_external_id' => $skippedNoExternalId,
        ]);

        return $mapped;
    }

    /**
     * @param  array<int, array<string, mixed>>  $mapped
     * @return array{0: int, 1: int}
     */
    protected function persistCategories(array $mapped): array
    {
        $this->categoryIdMap = [];
        $created = 0;
        $updated = 0;

        // Сортируем: сначала без родителя, затем по родителям
        $rootKey = '__root__';
        $byParent = [];
        foreach ($mapped as $row) {
            $pid = $row['parent_external_id'] ?? $rootKey;
            if ($pid === null) {
                $pid = $rootKey;
            }
            $byParent[$pid][] = $row;
        }
        $queue = $byParent[$rootKey] ?? [];
        $processed = [];
        while (! empty($queue)) {
            $row = array_shift($queue);
            $slug = $row['slug'];
            $parentId = null;
            if (! empty($row['parent_external_id']) && isset($this->categoryIdMap[$row['parent_external_id']])) {
                $parentId = $this->categoryIdMap[$row['parent_external_id']];
            }

            $category = Category::updateOrCreate(
                ['slug' => $slug, 'taxonomy_id' => $this->taxonomyId],
                [
                    'name' => $row['name'],
                    'slug' => $slug,
                    'taxonomy_id' => $this->taxonomyId,
                    'description' => $row['description'] ?? null,
                    'is_active' => true,
                    'priority' => $row['priority'],
                    'parent_id' => $parentId,
                ]
            );

            if ($category->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            if (! empty($row['external_id'])) {
                $this->categoryIdMap[$row['external_id']] = $category->id;
            }
            $processed[$row['external_id'] ?? $slug] = true;

            $extId = $row['external_id'] ?? null;
            foreach ($extId !== null ? ($byParent[$extId] ?? []) : [] as $child) {
                $queue[] = $child;
            }
        }

        // Обработать категории, чей родитель не найден (создать с parent_id = null)
        foreach ($mapped as $row) {
            $key = $row['external_id'] ?? $row['slug'];
            if (isset($processed[$key])) {
                continue;
            }
            $parentId = null;
            if (! empty($row['parent_external_id']) && isset($this->categoryIdMap[$row['parent_external_id']])) {
                $parentId = $this->categoryIdMap[$row['parent_external_id']];
            }
            $category = Category::updateOrCreate(
                ['slug' => $row['slug'], 'taxonomy_id' => $this->taxonomyId],
                [
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'taxonomy_id' => $this->taxonomyId,
                    'description' => $row['description'] ?? null,
                    'is_active' => true,
                    'priority' => $row['priority'],
                    'parent_id' => $parentId,
                ]
            );
            if ($category->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
            if (! empty($row['external_id'])) {
                $this->categoryIdMap[$row['external_id']] = $category->id;
            }
        }

        return [$created, $updated];
    }

    /**
     * @param  array<int, array<string, mixed>>  $mapped
     * @return array{0: int, 1: int}
     */
    protected function persistProducts(array $mapped): array
    {
        $created = 0;
        $updated = 0;

        Log::info('Catalog import: persistProducts started', [
            'rows' => count($mapped),
        ]);

        $this->ensureManufacturersLoaded();

        foreach ($mapped as $row) {
            if (! empty($row['external_id'])) {
                $this->enrichRowFromProductDetail($row);
            }

            $slug = $row['slug'] ?? Str::slug($row['name']);
            $taxonIds = [];
            foreach ($row['category_external_ids'] as $extId) {
                if (isset($this->categoryIdMap[$extId])) {
                    $taxonIds[] = $this->categoryIdMap[$extId];
                }
            }
            $taxonIds = array_unique($taxonIds);

            $product = $this->findOrResolveProduct($row);

            if ($product === null) {
                $product = new Product;
                $product->sku = $row['sku'];
                $product->state = Product::ACTIVE;
                $product->stock = 0;
                $product->backorder = false;
                $product->units_sold = 0;
            }

            $this->applyProductDataFromImport($product, $row, $product->exists);
            $product->save();

            if ($product->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            // Для уже существующих товаров не меняем категории из импорта,
            // чтобы не затирать ручные правки в админке.
            if (! empty($taxonIds) && $product->wasRecentlyCreated) {
                $product->taxons()->syncWithoutDetaching($taxonIds);
            }

            $this->syncProductManufacturer($product, $row);

            // Модификации (торговые предложения) — запрос по external_id товара
            $productExternalId = $product->external_id ?? $row['external_id'] ?? null;
            if ($productExternalId !== null && $productExternalId !== '') {
                try {
                    [$vCreated, $vUpdated] = $this->persistModifications($product, (string) $productExternalId);
                    $created += $vCreated;
                    $updated += $vUpdated;
                } catch (\Throwable $e) {
                    Log::warning('Catalog import: modifications fetch failed for product ' . $productExternalId, [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->syncProductCharacteristics($product, (string) ($row['external_id'] ?? ''));
            $this->syncProductPriceFromCache($product, (string) ($row['external_id'] ?? ''));
            $this->syncProductWarehouseStocks($product, (string) ($row['external_id'] ?? ''));
        }

        // Синхронизация остатков из 1С (syncStockItemsBulkFrom1C и аналогичные эндпоинты)
        if ($this->stockPath !== '') {
            try {
                $this->fetchAndApplyStock();
            } catch (\Throwable $e) {
                Log::warning('Catalog import: stock sync failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Catalog import: persistProducts finished', [
            'created' => $created,
            'updated' => $updated,
        ]);

        return [$created, $updated];
    }

    /**
     * Привязать к товару атрибут «Производитель» и связь manufacturer_id по данным из строки импорта.
     *
     * @param  array<string, mixed>  $row
     */
    protected function syncProductManufacturer(Product $product, array $row): void
    {
        $resolved = $this->resolveManufacturerFromRow($row);

        $this->detachManufacturerAttribute($product);

        if ($resolved === null) {
            $product->manufacturer_id = null;
            $product->saveQuietly();
            Log::debug('Catalog import: product without manufacturer', [
                'product_id' => $product->id,
                'sku' => $product->sku,
            ]);

            return;
        }

        $this->attachManufacturerAttributeValue($product, $resolved['name']);
        $product->manufacturer_id = $resolved['our_id']; // для фильтров в админке и API
        $product->saveQuietly();
    }

    protected function syncProductCharacteristics(Product $product, string $externalId): void
    {
        if ($externalId === '') {
            return;
        }

        $items = $this->fetchProductCharacteristicsRaw($externalId);
        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $values = $item['values'] ?? [];
            $value = is_array($values) ? trim((string) ($values[0] ?? '')) : trim((string) $values);
            if ($value === '') {
                continue;
            }

            $attribute = Attribute::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'type' => 'text',
                    'is_filterable' => true,
                    'is_use_in_variations' => false,
                    'allow_custom_value' => true,
                    'sort_order' => 0,
                ]
            );

            $valueSlug = Str::slug($value);
            if ($valueSlug === '') {
                $valueSlug = 'v-' . substr(md5($value), 0, 8);
            }

            $attributeValue = AttributeValue::firstOrCreate(
                ['attribute_id' => $attribute->id, 'slug' => $valueSlug],
                ['value' => $value, 'sort_order' => 0]
            );

            $product->attributes()->syncWithoutDetaching([
                $attribute->id => ['attribute_value_id' => $attributeValue->id, 'custom_value' => null],
            ]);
        }
    }

    protected function syncProductPriceFromCache(Product $product, string $externalId): bool
    {
        if ($externalId === '') {
            return false;
        }

        $price = $this->fetchProductPriceRaw($externalId);
        if ($price === null) {
            return false;
        }

        $product->price = $price;
        $product->saveQuietly();

        return true;
    }

    protected function syncProductWarehouseStocks(Product $product, string $externalId): void
    {
        if ($externalId === '') {
            return;
        }

        $rows = $this->fetchProductStocksRaw($externalId);
        Log::info('Catalog import: stocks raw data', [
            'external_id' => $externalId,
            'rows' => $rows,
        ]);
        foreach ($rows as $row) {
            $stockId = (string) ($row['stockId'] ?? '');
            if ($stockId === '') {
                continue;
            }
            $count = $this->adaptStockQuantity($row['count'] ?? 0);

            $warehouse = Warehouse::updateOrCreate(
                ['external_id' => $stockId],
                ['name' => (string) ($row['stockName'] ?? $stockId), 'is_active' => true]
            );

            ProductWarehouseStock::withoutEvents(function () use ($product, $warehouse, $count): void {
                ProductWarehouseStock::updateOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                    ['quantity' => $count]
                );
            });
        }

        // 🔥 Пересчитываем общий остаток и обновляем поле stock у товара
        $totalStock = ProductWarehouseStock::where('product_id', $product->id)->sum('quantity');
        $product->stock = $totalStock;
        Log::info('Catalog import: saving product stock', [
            'product_id' => $product->id,
            'total_stock' => $totalStock,
        ]);
        $product->saveQuietly();

        Log::info('Catalog import: product stock updated', [
            'product_id' => $product->id,
            'total_stock' => $totalStock,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchProductCharacteristicsRaw(string $externalId): array
    {
        $url = $this->baseUrl . '/api/v1/integration/1c/v2/cache/products/' . urlencode($externalId) . '/characteristics';
        $response = Http::timeout($this->timeout)->withHeaders($this->requestHeaders())->get($url);
        if (! $response->successful()) {
            Log::warning('Catalog import: characteristics fetch failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);

            return [];
        }

        $data = $response->json();
        $items = is_array($data) ? array_values($data) : [];
        Log::info('Catalog import: characteristics fetched', [
            'external_id' => $externalId,
            'count' => count($items),
        ]);

        return $items;
    }

    protected function fetchProductPriceRaw(string $externalId): ?float
    {
        $url = $this->baseUrl . '/api/v1/integration/1c/v2/cache/products/' . urlencode($externalId) . '/price';
        $response = Http::timeout($this->timeout)->withHeaders($this->requestHeaders())->get($url);
        if (! $response->successful()) {
            Log::warning('Catalog import: price fetch failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);

            return null;
        }

        $data = $response->json();
        if (! is_array($data) || ! array_key_exists('price', $data)) {
            Log::warning('Catalog import: price response invalid', [
                'external_id' => $externalId,
                'payload' => $data,
            ]);
            return null;
        }

        Log::info('Catalog import: price fetched', [
            'external_id' => $externalId,
            'price' => $data['price'],
        ]);

        return (float) $data['price'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchProductStocksRaw(string $externalId): array
    {
        $url = $this->baseUrl . '/api/v1/integration/1c/v2/cache/products/' . urlencode($externalId) . '/stocks';
        $response = Http::timeout($this->timeout)->withHeaders($this->requestHeaders())->get($url);
        if (! $response->successful()) {
            Log::warning('Catalog import: stocks fetch failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);

            return [];
        }

        $data = $response->json();
        $items = is_array($data) ? array_values($data) : [];
        Log::info('Catalog import: stocks fetched', [
            'external_id' => $externalId,
            'count' => count($items),
        ]);

        return $items;
    }

    /**
     * Разрешить производителя из строки импорта: имя + наш id в БД (id может быть null, если найден только по имени).
     * Использует manufacturer_name, manufacturer_external_id или manufacturer_id (API) и справочник производителей.
     *
     * @param  array<string, mixed>  $row
     * @return array{name: string, our_id: int|null}|null
     */
    protected function resolveManufacturerFromRow(array $row): ?array
    {
        $name = $row['manufacturer_name'] ?? null;
        $ourId = null;

        if (($name === null || $name === '') && ! empty($row['manufacturer_external_id'])) {
            $ourId = $this->manufacturerIdByExternalId[$row['manufacturer_external_id']] ?? null;
            if ($ourId !== null) {
                $name = Manufacturer::find($ourId)?->name;
            }
        }
        if (($name === null || $name === '') && isset($row['manufacturer_id'])) {
            $lookupKey = $this->manufacturerIdToExternalId[$row['manufacturer_id']] ?? null;
            if ($lookupKey !== null) {
                $ourId = $this->manufacturerIdByExternalId[$lookupKey] ?? null;
                if ($ourId !== null) {
                    $name = Manufacturer::find($ourId)?->name;
                }
            }
        }

        if ($name === null || $name === '') {
            return null;
        }

        $name = trim($name);
        if ($ourId === null) {
            $manufacturer = Manufacturer::where('name', $name)->first();
            $ourId = $manufacturer?->id;
        }

        return ['name' => $name, 'our_id' => $ourId];
    }

    /**
     * Привязать к товару атрибут «Производитель» с заданным значением (имя).
     */
    protected function attachManufacturerAttributeValue(Product $product, string $name): void
    {
        $attr = Attribute::firstOrCreate(
            ['slug' => Attribute::SLUG_MANUFACTURER],
            [
                'name' => 'Производитель',
                'type' => 'text',
                'is_filterable' => false,
                'is_use_in_variations' => false,
                'allow_custom_value' => true,
                'sort_order' => 0,
            ]
        );
        $valueSlug = Str::slug($name);
        if ($valueSlug === '') {
            $valueSlug = 'm-' . substr(md5($name), 0, 8);
        }
        $attrValue = AttributeValue::firstOrCreate(
            [
                'attribute_id' => $attr->id,
                'slug' => $valueSlug,
            ],
            ['value' => $name, 'sort_order' => 0]
        );

        $product->attributes()->attach($attr->id, ['attribute_value_id' => $attrValue->id]);
    }

    /**
     * Убрать у товара атрибут «Производитель».
     */
    protected function detachManufacturerAttribute(Product $product): void
    {
        $attr = Attribute::where('slug', Attribute::SLUG_MANUFACTURER)->first();
        if ($attr !== null) {
            $product->attributes()->where('product_attributes.id', $attr->id)->detach();
        }
    }

    /**
     * Загрузить остатки из API (bulk) и применить к товарам/вариациям по external_id.
     * Эндпоинт задаётся в config: stock_path (например /api/v1/integration/1c/stock-items).
     */
    protected function fetchAndApplyStock(): void
    {
        $raw = $this->fetchStockRaw();
        $items = $this->mapStockItems($raw);
        $this->applyStockFromImport($items);
    }

    /**
     * Загрузить сырой ответ остатков (bulk). Переопределите под формат API.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchStockRaw(): array
    {
        $url = $this->baseUrl . '/' . $this->stockPath;
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->requestHeaders())
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('API stock request failed: ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [];
        }
        $items = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        return array_values($items);
    }

    /**
     * Маппинг элемента остатков: external_id и stock. Переопределите под формат API.
     *
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array{external_id: string, stock: int}>
     */
    protected function mapStockItems(array $raw): array
    {
        $mapped = [];
        foreach ($raw as $item) {
            $externalId = $item['externalId'] ?? $item['external_id'] ?? null;
            if ($externalId === null || $externalId === '') {
                continue;
            }
            $stock = isset($item['stock']) ? (int) $item['stock'] : (isset($item['quantity']) ? (int) $item['quantity'] : null);
            if ($stock === null) {
                continue;
            }
            $mapped[] = ['external_id' => (string) $externalId, 'stock' => (int) $stock];
        }

        return $mapped;
    }

    /**
     * Применить остатки к товарам и вариациям по external_id.
     * external_id может быть как у родительского товара, так и у модификации (торгового предложения) —
     * в последнем случае обновляется вариация, что позволяет синхронизировать остатки по модификаторам из 1С.
     *
     * @param  array<int, array{external_id: string, stock: int}>  $items
     */
    protected function applyStockFromImport(array $items): void
    {
        foreach ($items as $row) {
            Product::query()
                ->where('external_id', $row['external_id'])
                ->update(['stock' => $row['stock']]);
        }
    }

    /**
     * Загрузить модификации (торговые предложения) товара по его external_id.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchModificationsRaw(string $productExternalId): array
    {
        $url = $this->baseUrl . '/api/v1/integration/1c/products/' . urlencode($productExternalId) . '/modifications';
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->requestHeaders())
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('API modifications request failed: ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [];
        }
        // Ответ API: { productId, productExternalId, productName, modifications: [...], totalCount }
        $items = isset($data['modifications']) && is_array($data['modifications']) ? $data['modifications'] : [];

        return array_values($items);
    }

    /**
     * Внешний идентификатор 1С модификации (поле externalId из API).
     *
     * @param  array<string, mixed>  $item
     */
    protected function getModificationExternalId(array $item): ?string
    {
        $value = $item['externalId'] ?? $item['external_id'] ?? null;

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    protected function mapModifications(array $raw): array
    {
        $mapped = [];
        foreach ($raw as $item) {
            $name = $this->decodeHtml((string) ($item['name'] ?? $item['title'] ?? '')) ?? '';
            if ($name === '') {
                continue;
            }
            $externalId = $this->getModificationExternalId($item);
            $sku = $item['sku'] ?? $item['article'] ?? $externalId ?? Str::slug($name);
            $desc = $item['description'] ?? null;
            $excerpt = $item['excerpt'] ?? $item['shortDescription'] ?? null;
            $mapped[] = [
                'name' => $name,
                'sku' => (string) $sku,
                'slug' => $this->slugFrom($item['slug'] ?? null, $name),
                'price' => (float) ($item['basePrice'] ?? $item['price'] ?? $item['price_old'] ?? 0),
                'original_price' => isset($item['originalPrice']) ? (float) $item['originalPrice'] : null,
                'description' => $desc !== null ? $this->decodeHtml((string) $desc) : null,
                'excerpt' => $excerpt !== null ? $this->decodeHtml((string) $excerpt) : null,
                'external_id' => $externalId,
                'stock' => array_key_exists('stock', $item) ? (int) $item['stock'] : null,
                'backorder' => array_key_exists('backorder', $item) ? (bool) $item['backorder'] : null,
            ];
        }

        return $mapped;
    }

    /**
     * Сохранить модификации товара (торговые предложения). Родитель помечается is_variable = true.
     *
     * @return array{0: int, 1: int} [created, updated]
     */
    protected function persistModifications(Product $parentProduct, string $productExternalId): array
    {
        $raw = $this->fetchModificationsRaw($productExternalId);
        $mapped = $this->mapModifications($raw);

        $created = 0;
        $updated = 0;

        if ($mapped !== []) {
            $parentProduct->is_variable = true;
            $parentProduct->saveQuietly();
        }

        foreach ($mapped as $row) {
            $variant = $this->findOrResolveVariant($parentProduct, $row);

            if ($variant === null) {
                $variant = new Product;
                $variant->parent_product_id = $parentProduct->id;
                $variant->sku = $row['sku'];
                $variant->state = Product::ACTIVE;
                $variant->stock = 0;
                $variant->backorder = false;
                $variant->units_sold = 0;
            }

            $this->applyVariantDataFromImport($variant, $row, $variant->exists);
            $variant->save();

            if ($variant->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        return [$created, $updated];
    }

    /**
     * Ищем вариацию по external_id (если есть в импорте), иначе по sku, только среди вариаций данного родителя.
     *
     * @param  array<string, mixed>  $row
     */
    protected function findOrResolveVariant(Product $parentProduct, array $row): ?Product
    {
        $query = Product::query()->where('parent_product_id', $parentProduct->id);

        $externalId = $row['external_id'] ?? null;
        if ($externalId !== null && $externalId !== '') {
            $found = (clone $query)->where('external_id', (string) $externalId)->first();
            if ($found !== null) {
                return $found;
            }
        }

        $sku = $row['sku'] ?? null;
        if ($sku !== null && $sku !== '') {
            return $query->where('sku', (string) $sku)->first();
        }

        return null;
    }

    /**
     * Заполняет вариацию данными из импорта. При обновлении меняет только те поля, которые есть в импорте.
     *
     * @param  array<string, mixed>  $row
     */
    protected function applyVariantDataFromImport(Product $variant, array $row, bool $isUpdate): void
    {
        $variant->name = (string) ($row['name'] ?? $variant->name ?? '');
        $variant->slug = (string) ($row['slug'] ?? Str::slug($row['name'] ?? $variant->name ?? ''));
        $variant->price = (float) ($row['price'] ?? $variant->price ?? 0);

        if (! $isUpdate) {
            $variant->original_price = array_key_exists('original_price', $row) ? $row['original_price'] : null;
            $variant->description = array_key_exists('description', $row) ? $row['description'] : null;
            $variant->excerpt = array_key_exists('excerpt', $row) ? $row['excerpt'] : null;
            if (isset($row['external_id']) && $row['external_id'] !== null && $row['external_id'] !== '') {
                $variant->external_id = (string) $row['external_id'];
            }
            if (array_key_exists('stock', $row) && $row['stock'] !== null) {
                $variant->stock = (int) $row['stock'];
            }
            if (array_key_exists('backorder', $row) && $row['backorder'] !== null) {
                $variant->backorder = (bool) $row['backorder'];
            }
            return;
        }

        if (array_key_exists('original_price', $row)) {
            $variant->original_price = $row['original_price'] !== null && $row['original_price'] !== ''
                ? (float) $row['original_price']
                : null;
        }
        if (array_key_exists('description', $row)) {
            $variant->description = $row['description'] !== null ? (string) $row['description'] : null;
        }
        if (array_key_exists('excerpt', $row)) {
            $variant->excerpt = $row['excerpt'] !== null ? (string) $row['excerpt'] : null;
        }
        if (isset($row['external_id']) && $row['external_id'] !== null && $row['external_id'] !== '') {
            $variant->external_id = (string) $row['external_id'];
        }
        if (array_key_exists('stock', $row) && $row['stock'] !== null) {
            $variant->stock = (int) $row['stock'];
        }
        if (array_key_exists('backorder', $row) && $row['backorder'] !== null) {
            $variant->backorder = (bool) $row['backorder'];
        }
    }

    /**
     * Ищем товар по external_id (если есть в импорте), иначе по sku (только родительские товары).
     *
     * @param  array<string, mixed>  $row
     */
    protected function findOrResolveProduct(array $row): ?Product
    {
        $query = Product::query()->whereNull('parent_product_id');
        $externalId = $row['external_id'] ?? null;
        if ($externalId !== null && $externalId !== '') {
            $found = (clone $query)->where('external_id', (string) $externalId)->first();
            if ($found !== null) {
                return $found;
            }
        }

        $sku = $row['sku'] ?? null;
        if ($sku !== null && $sku !== '') {
            return $query->where('sku', (string) $sku)->first();
        }

        return null;
    }

    /**
     * Заполняет модель товара данными из импорта. При обновлении меняет только те поля, которые есть в импорте.
     *
     * @param  array<string, mixed>  $row
     */
    protected function applyProductDataFromImport(Product $product, array $row, bool $isUpdate): void
    {
        $payloadKeys = isset($row['_payload_keys']) && is_array($row['_payload_keys']) ? $row['_payload_keys'] : [];

        // Для существующих товаров не перезаписываем вручную правленные name/slug.
        // Обновляем только операционные данные из импорта.
        if (! $isUpdate) {
            $product->name = (string) ($row['name'] ?? $product->name ?? '');
            $product->slug = (string) ($row['slug'] ?? Str::slug($row['name'] ?? $product->name ?? ''));
        }

        if (! $isUpdate || in_array('price', $payloadKeys, true) || in_array('basePrice', $payloadKeys, true)) {
            $product->price = (float) ($row['price'] ?? $product->price ?? 0);
        }

        if (! $isUpdate) {
            $product->original_price = array_key_exists('original_price', $row) ? $row['original_price'] : null;
            $product->description = array_key_exists('description', $row) ? $row['description'] : null;
            $product->excerpt = array_key_exists('excerpt', $row) ? $row['excerpt'] : null;
            if (isset($row['external_id']) && $row['external_id'] !== null && $row['external_id'] !== '') {
                $product->external_id = (string) $row['external_id'];
            }
            return;
        }

        if (array_key_exists('original_price', $row)) {
            $product->original_price = $row['original_price'] !== null && $row['original_price'] !== ''
                ? (float) $row['original_price']
                : null;
        }
        if (array_key_exists('description', $row) && in_array('description', $payloadKeys, true)) {
            $product->description = $row['description'] !== null ? (string) $row['description'] : null;
        }
        if (array_key_exists('excerpt', $row) && (in_array('excerpt', $payloadKeys, true) || in_array('shortDescription', $payloadKeys, true))) {
            $product->excerpt = $row['excerpt'] !== null ? (string) $row['excerpt'] : null;
        }
        if (isset($row['external_id']) && ($row['external_id'] !== null && $row['external_id'] !== '')) {
            $product->external_id = (string) $row['external_id'];
        }
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        return $headers;
    }

    private function getTaxonomyId(): int
    {
        $taxonomy = Taxonomy::firstOrCreate(
            ['slug' => 'product-categories'],
            ['name' => 'Product Categories', 'slug' => 'product-categories']
        );

        return (int) $taxonomy->id;
    }

    private function slugFrom(?string $slug, string $name): string
    {
        if ($slug !== null && $slug !== '') {
            return Str::slug($slug);
        }

        return Str::slug($name);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, string|int>
     */
    private function normalizeCategoryIds(array $item): array
    {
        $ids = [];
        if (isset($item['categoryId'])) {
            $ids[] = $item['categoryId'];
        }
        if (isset($item['category_id'])) {
            $ids[] = $item['category_id'];
        }
        if (isset($item['categoryIds']) && is_array($item['categoryIds'])) {
            $ids = array_merge($ids, $item['categoryIds']);
        }
        if (isset($item['category_ids']) && is_array($item['category_ids'])) {
            $ids = array_merge($ids, $item['category_ids']);
        }

        return array_values(array_unique($ids));
    }

    /**
     * Адаптер входящих остатков из API.
     * Внешнее API иногда отдаёт "грязные" дробные значения.
     * Нормализуем до целого количества единиц товара.
     */
    private function adaptStockQuantity(mixed $rawCount): float
    {
        $numeric = is_numeric($rawCount) ? (float) $rawCount : 0.0;
        $rounded = (float) round($numeric, 0, PHP_ROUND_HALF_UP);

        if ($rounded !== $numeric) {
            Log::warning('Catalog import: stock quantity rounded by adapter', [
                'raw_count' => $rawCount,
                'rounded_count' => $rounded,
            ]);
        }

        return max(0.0, $rounded);
    }
}
