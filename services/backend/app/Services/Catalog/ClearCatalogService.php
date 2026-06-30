<?php

namespace App\Services\Catalog;

use App\Models\Product\Category;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Временный сервис полной очистки каталога: все товары и категории.
 * Использовать только по явному действию админа (отдельная страница в админке).
 */
class ClearCatalogService
{
    public function clearAll(): array
    {
        $counts = [
            'products' => 0,
            'categories' => 0,
        ];

        DB::transaction(function () use (&$counts) {
            $counts['products'] = $this->deleteAllProducts();
            $counts['categories'] = $this->deleteAllCategories();
        });

        return $counts;
    }

    /** Удалить только товары (категории не трогает). */
    public function clearProductsOnly(): int
    {
        $count = 0;
        DB::transaction(function () use (&$count) {
            $count = $this->deleteAllProducts();
        });

        return $count;
    }

    /** Удалить только категории (товары не трогает). */
    public function clearCategoriesOnly(): int
    {
        $count = 0;
        DB::transaction(function () use (&$count) {
            $count = $this->deleteAllCategories();
        });

        return $count;
    }

    private function deleteAllProducts(): int
    {
        if (! Schema::hasTable('products')) {
            return 0;
        }

        // Порядок удаления из-за внешних ключей: связи товаров, затем товары

        $tablesToClear = [
            'product_region_rules',
            'reviews',
            'product_product_attributes',
            'product_related_products',
            'product_product_collection',
            'product_variant_attributes',
            'product_variation_attribute_selection',
        ];

        foreach ($tablesToClear as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        // Связь товар — категория (morph)
        if (Schema::hasTable('model_taxons')) {
            DB::table('model_taxons')
                ->where('model_type', Product::class)
                ->delete();
        }

        // Медиа товаров (Spatie Media Library)
        if (Schema::hasTable('media')) {
            DB::table('media')
                ->where('model_type', Product::class)
                ->delete();
        }

        $count = Product::query()->count();
        Product::query()->delete();

        return $count;
    }

    private function deleteAllCategories(): int
    {
        if (! Schema::hasTable('taxons')) {
            return 0;
        }

        $count = Category::query()->count();

        // Связи категорий
        $categoryTables = [
            'category_feature_blocks',
            'category_delivery_blocks',
            'category_variation_attributes',
        ];

        foreach ($categoryTables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        // Pivot model_taxons (связь категория — что угодно, в т.ч. товары уже удалены выше)
        if (Schema::hasTable('model_taxons')) {
            DB::table('model_taxons')->delete();
        }

        // Медиа категорий
        if (Schema::hasTable('media')) {
            DB::table('media')
                ->where('model_type', Category::class)
                ->delete();
        }

        // Категории: сначала обнуляем parent_id, затем удаляем (избегаем FK по parent_id)
        DB::table('taxons')->update(['parent_id' => null]);
        Category::query()->delete();

        return $count;
    }
}
