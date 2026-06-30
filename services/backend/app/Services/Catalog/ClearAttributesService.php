<?php

namespace App\Services\Catalog;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Временный сервис удаления всех характеристик (атрибутов) и их значений.
 * Использовать только по явному действию админа (сервисная страница в админке).
 */
class ClearAttributesService
{
    public function clearAll(): int
    {
        $count = Attribute::query()->count();

        Schema::disableForeignKeyConstraints();

        // Пивот-таблицы каталога.
        $tablesToClear = [
            'product_product_attributes',
            'product_variant_attributes',
            'product_variation_attribute_selection',
            'category_variation_attributes',
        ];

        foreach ($tablesToClear as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        // Сначала значения, потом сами атрибуты (FK attribute_id → product_attributes).
        if (Schema::hasTable('product_attribute_values')) {
            DB::table('product_attribute_values')->delete();
        }

        if (Schema::hasTable('product_attributes')) {
            DB::table('product_attributes')->delete();
        }

        Schema::enableForeignKeyConstraints();

        // Один общий сброс кэша товаров после очистки.
        Product::flushAllProductCaches();

        return $count;
    }
}
