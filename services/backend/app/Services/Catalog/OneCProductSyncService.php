<?php

namespace App\Services\Catalog;

use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Services\Catalog\Integrations\Svetofor1CCatalogImport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class OneCProductSyncService extends Svetofor1CCatalogImport
{
    /**
     * Переопределяем родительский метод: получаем детали товара из кэш-эндпоинта API,
     * а не из основного API.
     */
    protected function fetchProductDetailByExternalId(string $externalId): ?array
    {
        $url = $this->baseUrl . '/api/v1/integration/1c/v2/cache/products/' . urlencode($externalId);
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->requestHeaders())
            ->get($url);

        if (!$response->successful()) {
            Log::debug('1C sync: product detail from cache API failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        if (!is_array($data) || empty($data)) {
            return null;
        }

        // Структура ответа: { id, externalId, payload: { ... }, price, createdAt, updatedAt }
        $payload = $data['payload'] ?? [];
        $price = $data['price'] ?? null;

        // Формируем массив, совместимый с buildRowFromDetail
        return [
            'name'          => $payload['name'] ?? null,
            'sku'           => $payload['sku'] ?? $payload['article'] ?? null,
            'basePrice'     => $payload['basePrice'] ?? $price,
            'originalPrice' => $payload['originalPrice'] ?? null,
            'description'   => $payload['description'] ?? null,
            'shortDescription' => $payload['shortDescription'] ?? null,
            'manufacturerId' => $payload['manufacturerId'] ?? null,
            'manufacturer'  => ['name' => $payload['manufacturer']['name'] ?? null],
            'categoryIds'   => $payload['categoryIds'] ?? $payload['category_id'] ?? [],
            // можно добавить imageUrl: $payload['images'][0] ?? null
        ];
    }

    /**
     * Переопределяем родительский метод: используем числовой ID из кэша,
     * а не externalId, для запроса модификаций.
     *
     * @throws \RuntimeException
     */
    protected function fetchModificationsRaw(string $productExternalId): array
    {
        // 1. Сначала получаем кэш-данные товара, чтобы достать числовой ID
        $cacheUrl = $this->baseUrl . '/api/v1/integration/1c/v2/cache/products/' . urlencode($productExternalId);
        $cacheResponse = Http::timeout($this->timeout)
            ->withHeaders($this->requestHeaders())
            ->get($cacheUrl);

        if (!$cacheResponse->successful()) {
            throw new \RuntimeException(
                "Failed to get product from cache: " . $cacheResponse->status() . ' ' . $cacheResponse->body()
            );
        }

        $cacheData = $cacheResponse->json();
        $productId = $cacheData['id'] ?? null;
        if (!$productId) {
            throw new \RuntimeException("Product ID not found in cache response for externalId: {$productExternalId}");
        }

        // 2. Запрашиваем модификации по числовому ID
        $modUrl = $this->baseUrl . '/api/v1/integration/1c/products/' . $productId . '/modifications';
        $modResponse = Http::timeout($this->timeout)
            ->withHeaders($this->requestHeaders())
            ->get($modUrl);

        if (!$modResponse->successful()) {
            throw new \RuntimeException(
                "API modifications request failed: " . $modResponse->status() . ' ' . $modResponse->body()
            );
        }

        $data = $modResponse->json();
        $items = isset($data['modifications']) && is_array($data['modifications']) ? $data['modifications'] : [];
        return array_values($items);
    }

    protected function applyProductDataFromImport(Product $product, array $row, bool $isUpdate): void
    {
        parent::applyProductDataFromImport($product, $row, $isUpdate);

        if (!$isUpdate) {
            if (empty($product->sku)) {
                $product->sku = $row['sku'] ?? $row['external_id'] ?? null;
            }
            if (empty($product->slug)) {
                $product->slug = $row['slug'] ?? \Str::slug($row['name'] ?? '');
            }
        }
    }

    /**
     * Синхронизировать товар из 1С по external_id
     * Создаёт новый или обновляет существующий товар и его вариации.
     */
    public function syncProductByExternalId(string $externalId): ?Product
    {
        // 1. Получить детали товара из API
        $detail = $this->fetchProductDetailByExternalId($externalId);
        if (!$detail) {
            Log::warning('1C sync: product not found', ['external_id' => $externalId]);
            return null;
        }

        // 2. Найти или создать товар (родительский)
        $product = Product::where('external_id', $externalId)->first();
        $isNew = false;
        if (!$product) {
            $product = new Product();
            $isNew = true;
            $product->state = Product::ACTIVE;
            $product->stock = 0;
            $product->backorder = false;
            $product->units_sold = 0;
        }

        // 3. Заполнить данными из импорта
        $row = $this->buildRowFromDetail($detail, $externalId);
        $this->applyProductDataFromImport($product, $row, !$isNew);
        $product->save();

        // 4. Синхронизировать категории (если есть)
        if (!empty($row['category_external_ids'])) {
            $taxonIds = $this->resolveCategoryIds($row['category_external_ids']);
            if (!empty($taxonIds)) {
                $product->taxons()->syncWithoutDetaching($taxonIds);
            }
        }

        // 5. Синхронизировать производителя
        $this->syncProductManufacturer($product, $row);

        // 6. Синхронизировать характеристики (attributes)
        $this->syncProductCharacteristics($product, $externalId);

        // 7. Синхронизировать цену (если не установлена через детали)
        if (!isset($row['price']) || $row['price'] == 0) {
            $this->syncProductPriceFromCache($product, $externalId);
        }

        // 8. Синхронизировать модификации (вариации).
        // Сначала создаём/обновляем их, чтобы затем одним проходом
        // подтянуть остатки и родителя, и каждой вариации по всем складам.
        $this->syncModifications($product, $externalId);

        // 9. Синхронизировать остатки по складам для всего дерева товара.
        // Для вариативного товара метод включает родителя и все вариации,
        // у которых заполнен external_id.
        $this->syncWarehouseStocksForProduct($product->fresh());

        // 10. (Опционально) Изображения – можно добавить, если API отдаёт URL
        // $this->syncProductImages($product, $detail);

        return $product;
    }

    /**
     * Преобразовать детали API в формат строки импорта.
     */
    protected function buildRowFromDetail(array $detail, string $externalId): array
    {
        return [
            'external_id' => $externalId,
            'name' => $detail['name'] ?? '',
            'sku' => $detail['sku'] ?? $detail['article'] ?? $externalId,
            'price' => $detail['basePrice'] ?? 0,
            'original_price' => $detail['originalPrice'] ?? null,
            'description' => $detail['description'] ?? null,
            'excerpt' => $detail['shortDescription'] ?? null,
            'manufacturer_id' => $detail['manufacturerId'] ?? null,
            'manufacturer_name' => $detail['manufacturer']['name'] ?? null,
            'category_external_ids' => $detail['categoryIds'] ?? [],
            // можно добавить image_url, если есть
        ];
    }

    /**
     * Получить внутренние ID категорий по внешним.
     */
    protected function resolveCategoryIds(array $externalIds): array
    {
        return Category::whereIn('external_id', $externalIds)->pluck('id')->toArray();
    }

    /**
     * Синхронизировать модификации (вариации) товара.
     */
    protected function syncModifications(Product $parentProduct, string $externalId): void
    {
        try {
            $raw = $this->fetchModificationsRaw($externalId);
            $mapped = $this->mapModifications($raw);
            if (empty($mapped)) {
                return;
            }

            $parentProduct->is_variable = true;
            $parentProduct->saveQuietly();

            foreach ($mapped as $row) {
                $variant = $this->findOrResolveVariant($parentProduct, $row);
                if (!$variant) {
                    $variant = new Product();
                    $variant->parent_product_id = $parentProduct->id;
                    $variant->state = Product::ACTIVE;
                    $variant->stock = 0;
                    $variant->backorder = false;
                    $variant->units_sold = 0;
                }
                $this->applyVariantDataFromImport($variant, $row, $variant->exists);
                $variant->save();
            }
        } catch (\Exception $e) {
            Log::warning('1C sync: modifications fetch failed for product', [
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);
            // Продолжаем выполнение, не прерывая импорт
        }
    }
}
