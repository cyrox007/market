<?php

namespace App\Services\Catalog;

use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Services\Catalog\Integrations\Svetofor1CCatalogImport;
use Illuminate\Support\Facades\Log;

class OneCProductSyncService extends Svetofor1CCatalogImport
{
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

        // 8. Синхронизировать остатки по складам
        $this->syncProductWarehouseStocks($product, $externalId);

        // 9. Синхронизировать модификации (вариации)
        $this->syncModifications($product, $externalId);

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
    }
}