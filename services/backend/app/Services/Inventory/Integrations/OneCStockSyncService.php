<?php

declare(strict_types=1);

namespace App\Services\Inventory\Integrations;

use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Основной stock-only канал интеграции с 1С.
 *
 * Этот сервис намеренно ничего не знает об импорте каталога и никогда не
 * создаёт товары, не меняет цены, контент, категории, характеристики или фото.
 * Единственная зона ответственности — остатки существующих Product по external_id.
 */
class OneCStockSyncService
{
    private const DEFAULT_BASE_URL = 'http://api.svetofor-mebel.ru';

    private string $baseUrl;

    private string $apiKey;

    private int $timeout;

    private string $bulkPath;

    private string $productPathTemplate;

    public function __construct()
    {
        $cfg = config('catalog_import.config.svetofor_1c', []);
        $baseUrl = trim((string) ($cfg['base_url'] ?? ''));

        $this->baseUrl = rtrim($baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL, '/');
        $this->apiKey = (string) ($cfg['api_key'] ?? '');
        $this->timeout = max(1, (int) ($cfg['stock_sync_timeout'] ?? $cfg['timeout'] ?? 30));
        $this->bulkPath = ltrim((string) ($cfg['stock_sync_path'] ?? '/api/v1/integration/1c/v2/cache/stocks'), '/');
        $this->productPathTemplate = (string) ($cfg['stock_product_path_template']
            ?? '/api/v1/integration/1c/v2/cache/products/{external_id}/stocks');
    }

    /**
     * Точечное обновление товара. Для вариативного корневого товара также
     * обновляются его торговые предложения.
     *
     * @return array{targets:int,synced:int,skipped:int,errors:int,warehouse_rows:int}
     */
    public function syncProduct(Product $product): array
    {
        $targets = collect([$product]);

        if ($product->isVariable() && ! $product->isVariant()) {
            $targets = $targets->merge($product->variants()->get());
        }

        $result = $this->emptyResult();
        $result['targets'] = $targets->count();

        foreach ($targets as $target) {
            $externalId = trim((string) ($target->external_id ?? ''));

            if ($externalId === '') {
                $result['skipped']++;
                continue;
            }

            try {
                $stockRows = $this->fetchProductStocks($externalId);
                $applied = $this->applyStockRows($target, $stockRows);
                $result['synced']++;
                $result['warehouse_rows'] += $applied['warehouse_rows'];
            } catch (\Throwable $e) {
                $result['errors']++;

                Log::warning('1C stock sync: product failed', [
                    'product_id' => $target->id,
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('1C stock sync: product tree finished', [
            'root_product_id' => $product->id,
            ...$result,
        ]);

        return $result;
    }

    /**
     * Обновить один существующий товар/ТП по external_id.
     *
     * @return array{targets:int,synced:int,skipped:int,errors:int,warehouse_rows:int}
     */
    public function syncByExternalId(string $externalId): array
    {
        $externalId = trim($externalId);

        if ($externalId === '') {
            return [
                'targets' => 0,
                'synced' => 0,
                'skipped' => 1,
                'errors' => 0,
                'warehouse_rows' => 0,
            ];
        }

        $product = Product::query()
            ->where('external_id', $externalId)
            ->first();

        if ($product === null) {
            Log::notice('1C stock sync: external_id is absent in local catalog', [
                'external_id' => $externalId,
            ]);

            return [
                'targets' => 0,
                'synced' => 0,
                'skipped' => 1,
                'errors' => 0,
                'warehouse_rows' => 0,
            ];
        }

        // По external_id синхронизируем ровно найденную позицию. Это важно для
        // фоновой массовой очереди: каждое ТП получает только один job.
        try {
            $rows = $this->fetchProductStocks($externalId);
            $applied = $this->applyStockRows($product, $rows);

            return [
                'targets' => 1,
                'synced' => 1,
                'skipped' => 0,
                'errors' => 0,
                'warehouse_rows' => $applied['warehouse_rows'],
            ];
        } catch (\Throwable $e) {
            Log::warning('1C stock sync: external_id failed', [
                'product_id' => $product->id,
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            return [
                'targets' => 1,
                'synced' => 0,
                'skipped' => 0,
                'errors' => 1,
                'warehouse_rows' => 0,
            ];
        }
    }

    /**
     * Общий stock-only sync всей системы через bulk endpoint.
     * Неизвестные external_id игнорируются: этот поток никогда не создаёт Product.
     *
     * @return array{targets:int,synced:int,skipped:int,errors:int,warehouse_rows:int}
     */
    public function syncAll(): array
    {
        if ($this->bulkPath === '') {
            throw new \RuntimeException('1C stock sync bulk path is not configured.');
        }

        $groups = $this->fetchBulkStockGroups();
        $result = $this->emptyResult();
        $result['targets'] = count($groups);

        if ($groups === []) {
            return $result;
        }

        $products = Product::query()
            ->whereIn('external_id', array_keys($groups))
            ->get()
            ->keyBy(fn (Product $product): string => (string) $product->external_id);

        foreach ($groups as $externalId => $group) {
            /** @var Product|null $product */
            $product = $products->get($externalId);

            if ($product === null) {
                $result['skipped']++;
                continue;
            }

            try {
                $applied = $this->applyStockRows(
                    $product,
                    $group['stocks'],
                    $group['aggregate']
                );
                $result['synced']++;
                $result['warehouse_rows'] += $applied['warehouse_rows'];
            } catch (\Throwable $e) {
                $result['errors']++;

                Log::warning('1C stock sync: bulk row failed', [
                    'product_id' => $product->id,
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('1C stock sync: bulk finished', $result);

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchProductStocks(string $externalId): array
    {
        $path = str_replace('{external_id}', rawurlencode($externalId), $this->productPathTemplate);
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->requestHeaders())
            ->get($this->baseUrl . '/' . ltrim($path, '/'));

        if (! $response->successful()) {
            throw new \RuntimeException(
                "1C stock API failed for {$externalId}: {$response->status()} {$response->body()}"
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new \RuntimeException("1C stock API returned invalid JSON for {$externalId}.");
        }

        $items = $this->unwrapList($data);

        return array_values(array_filter($items, 'is_array'));
    }

    /**
     * @return array<string, array{stocks:list<array<string,mixed>>,aggregate:?float}>
     */
    private function fetchBulkStockGroups(): array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->requestHeaders())
            ->get($this->baseUrl . '/' . $this->bulkPath);

        if (! $response->successful()) {
            throw new \RuntimeException(
                '1C bulk stock API failed: ' . $response->status() . ' ' . $response->body()
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new \RuntimeException('1C bulk stock API returned invalid JSON.');
        }

        $items = $this->unwrapList($data);
        $groups = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $externalId = $this->extractProductExternalId($item);
            if ($externalId === null) {
                continue;
            }

            $groups[$externalId] ??= ['stocks' => [], 'aggregate' => null];

            $nested = $item['stocks'] ?? $item['warehouses'] ?? $item['stockItems'] ?? $item['stock_items'] ?? null;

            if (is_array($nested)) {
                foreach ($this->unwrapList($nested) as $stockRow) {
                    if (is_array($stockRow)) {
                        $groups[$externalId]['stocks'][] = $stockRow;
                    }
                }
            } elseif ($this->looksLikeWarehouseStockRow($item)) {
                $groups[$externalId]['stocks'][] = $item;
            } else {
                $aggregate = $this->extractAggregateQuantity($item);
                if ($aggregate !== null) {
                    $groups[$externalId]['aggregate'] = $aggregate;
                }
            }
        }

        return $groups;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{warehouse_rows:int,total_stock:float}
     */
    private function applyStockRows(Product $product, array $rows, ?float $aggregate = null): array
    {
        return DB::transaction(function () use ($product, $rows, $aggregate): array {
            $warehouseRows = 0;
            $seenWarehouseIds = [];

            foreach ($rows as $row) {
                $stockId = trim((string) ($row['stockId']
                    ?? $row['stock_id']
                    ?? $row['warehouseId']
                    ?? $row['warehouse_id']
                    ?? ''));

                if ($stockId === '') {
                    continue;
                }

                $quantity = $this->adaptQuantity(
                    $row['count'] ?? $row['quantity'] ?? $row['stock'] ?? 0
                );

                $warehouse = Warehouse::updateOrCreate(
                    ['external_id' => $stockId],
                    [
                        'name' => (string) ($row['stockName']
                            ?? $row['stock_name']
                            ?? $row['warehouseName']
                            ?? $row['warehouse_name']
                            ?? $stockId),
                        'is_active' => true,
                    ]
                );

                ProductWarehouseStock::withoutEvents(function () use ($product, $warehouse, $quantity): void {
                    ProductWarehouseStock::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'warehouse_id' => $warehouse->id,
                        ],
                        ['quantity' => $quantity]
                    );
                });

                $seenWarehouseIds[] = (int) $warehouse->id;
                $warehouseRows++;
            }

            // Endpoint товара отдаёт снимок остатков. Если склад пропал из ответа,
            // его старое значение нельзя оставлять: иначе WarehouseStockResolver
            // продолжит видеть устаревший положительный остаток.
            $staleRows = ProductWarehouseStock::query()
                ->where('product_id', $product->id)
                ->whereHas('warehouse', fn ($query) => $query
                    ->whereNotNull('external_id')
                    ->where('external_id', '!=', ''));

            if ($seenWarehouseIds !== []) {
                $staleRows->whereNotIn('warehouse_id', array_values(array_unique($seenWarehouseIds)));
            }

            ProductWarehouseStock::withoutEvents(function () use ($staleRows): void {
                $staleRows->update(['quantity' => 0]);
            });

            if ($warehouseRows > 0) {
                $totalStock = (float) ProductWarehouseStock::query()
                    ->where('product_id', $product->id)
                    ->sum('quantity');
            } elseif ($aggregate !== null) {
                $totalStock = $this->adaptQuantity($aggregate);
            } else {
                // Пустой корректный ответ означает нулевой остаток.
                $totalStock = 0.0;
            }

            $product->stock = $totalStock;
            $product->saveQuietly();
            $product->flushCache();

            return [
                'warehouse_rows' => $warehouseRows,
                'total_stock' => $totalStock,
            ];
        });
    }

    /**
     * @return list<mixed>
     */
    private function unwrapList(array $data): array
    {
        foreach (['data', 'items', 'stocks', 'results'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_values($data[$key]);
            }
        }

        return array_is_list($data) ? array_values($data) : [$data];
    }

    private function extractProductExternalId(array $item): ?string
    {
        $value = $item['productExternalId']
            ?? $item['product_external_id']
            ?? $item['externalId']
            ?? $item['external_id']
            ?? $item['productCode']
            ?? $item['product_code']
            ?? null;

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function looksLikeWarehouseStockRow(array $item): bool
    {
        return isset($item['stockId'])
            || isset($item['stock_id'])
            || isset($item['warehouseId'])
            || isset($item['warehouse_id']);
    }

    private function extractAggregateQuantity(array $item): ?float
    {
        foreach (['stock', 'quantity', 'count'] as $key) {
            if (array_key_exists($key, $item) && is_numeric($item[$key])) {
                return (float) $item[$key];
            }
        }

        return null;
    }

    /**
     * @return array<string,string>
     */
    private function requestHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];

        if ($this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        return $headers;
    }

    private function adaptQuantity(mixed $raw): float
    {
        $numeric = is_numeric($raw) ? (float) $raw : 0.0;

        return max(0.0, (float) round($numeric, 0, PHP_ROUND_HALF_UP));
    }

    /**
     * @return array{targets:int,synced:int,skipped:int,errors:int,warehouse_rows:int}
     */
    private function emptyResult(): array
    {
        return [
            'targets' => 0,
            'synced' => 0,
            'skipped' => 0,
            'errors' => 0,
            'warehouse_rows' => 0,
        ];
    }
}
