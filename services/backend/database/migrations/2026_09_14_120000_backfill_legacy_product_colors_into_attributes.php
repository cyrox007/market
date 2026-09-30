<?php

use App\Models\Product\Attribute;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasTable('product_attributes')
            || ! Schema::hasTable('product_attribute_values')
            || ! Schema::hasTable('product_product_attributes')
            || ! Schema::hasTable('product_variant_attributes')
            || ! Schema::hasColumn('products', 'color')) {
            return;
        }

        $legacyProducts = DB::table('products')
            ->whereNotNull('color')
            ->where('color', '!=', '')
            ->select('id', 'parent_product_id', 'color', 'color_code')
            ->get();

        if ($legacyProducts->isEmpty()) {
            return;
        }

        $now = now();
        $colorAttributeId = DB::table('product_attributes')
            ->where('slug', Attribute::SLUG_COLOR)
            ->value('id');

        if (! $colorAttributeId) {
            $colorAttributeId = DB::table('product_attributes')->insertGetId([
                'name' => 'Цвет',
                'slug' => Attribute::SLUG_COLOR,
                'type' => 'color',
                'is_filterable' => true,
                'is_use_in_variations' => true,
                'allow_custom_value' => false,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('product_attributes')
                ->where('id', $colorAttributeId)
                ->update([
                    'type' => 'color',
                    'is_filterable' => true,
                    'is_use_in_variations' => true,
                    'updated_at' => $now,
                ]);
        }

        foreach ($legacyProducts as $product) {
            $value = trim((string) $product->color);
            if ($value === '') {
                continue;
            }

            $slug = Str::slug($value);
            if ($slug === '') {
                $slug = 'color-' . substr(sha1(mb_strtolower($value)), 0, 12);
            }

            $attributeValue = DB::table('product_attribute_values')
                ->where('attribute_id', $colorAttributeId)
                ->where('slug', $slug)
                ->first();

            if (! $attributeValue) {
                $valueId = DB::table('product_attribute_values')->insertGetId([
                    'attribute_id' => $colorAttributeId,
                    'value' => $value,
                    'slug' => $slug,
                    'color_code' => $product->color_code ?: null,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $valueId = $attributeValue->id;
                if (empty($attributeValue->color_code) && ! empty($product->color_code)) {
                    DB::table('product_attribute_values')
                        ->where('id', $valueId)
                        ->update(['color_code' => $product->color_code, 'updated_at' => $now]);
                }
            }

            if ($product->parent_product_id) {
                DB::table('product_variant_attributes')->updateOrInsert(
                    ['product_id' => $product->id, 'attribute_id' => $colorAttributeId],
                    ['attribute_value_id' => $valueId, 'custom_value' => null, 'updated_at' => $now, 'created_at' => $now],
                );

                if (Schema::hasTable('product_variation_attribute_selection')) {
                    DB::table('product_variation_attribute_selection')->updateOrInsert(
                        ['product_id' => $product->parent_product_id, 'attribute_id' => $colorAttributeId],
                        ['updated_at' => $now, 'created_at' => $now],
                    );
                }
            } else {
                $exists = DB::table('product_product_attributes')
                    ->where('product_id', $product->id)
                    ->where('attribute_id', $colorAttributeId)
                    ->where('attribute_value_id', $valueId)
                    ->exists();

                if (! $exists) {
                    DB::table('product_product_attributes')->insert([
                        'product_id' => $product->id,
                        'attribute_id' => $colorAttributeId,
                        'attribute_value_id' => $valueId,
                        'custom_value' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Data migration: intentionally do not remove canonical values on rollback,
        // because they may already be referenced by products created after deployment.
    }
};
