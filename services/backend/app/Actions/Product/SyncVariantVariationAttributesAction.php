<?php

namespace App\Actions\Product;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncVariantVariationAttributesAction
{
    public function __construct(
        protected GetVariationAttributesForProductAction $getVariationAttributesForProductAction,
    ) {
    }

    /**
     * @param  list<int>|null  $forcedAttributeIds
     */
    public function execute(
        Product $variant,
        array $data,
        Product $parentProduct,
        ?array $forcedAttributeIds = null,
    ): void {
        \Log::info('[SyncVariantVariationAttributesAction] data', [
            'data' => $data,
            'variant_id' => $variant->id,
        ]);
        $attributes = $this->getVariationAttributesForProductAction->execute($parentProduct, $forcedAttributeIds);

        if ($attributes->isEmpty()) {
            Log::debug('[SyncVariantVariationAttributesAction] no attributes to sync', [
                'variant_id' => $variant->getKey(),
                'parent_id' => $parentProduct->getKey(),
            ]);

            return;
        }

        // Omitted fields must not clear values edited elsewhere or absent from this form.
        $attributes = $attributes->filter(fn (Attribute $attr) => array_key_exists('variation_attr_'.$attr->id, $data)
            || array_key_exists('variation_custom_'.$attr->id, $data));
        $attributeIds = $attributes->pluck('id')->all();
        $inserted = DB::transaction(function () use ($variant, $attributes, $attributeIds, $data): int {
            DB::table('products')->where('id', $variant->id)->lockForUpdate()->first();
            foreach ($attributes as $attr) {
                $key = 'variation_attr_'.$attr->id;
                $rules = [
                    'variation_custom_'.$attr->id => 'nullable|string',
                    $key => $attr->is_multiple ? 'nullable|array' : 'nullable|integer',
                ];
                $valueRule = \Illuminate\Validation\Rule::exists('product_attribute_values', 'id')->where('attribute_id', $attr->id);
                $rules[$attr->is_multiple ? $key.'.*' : $key] = $attr->is_multiple
                    ? ['integer', $valueRule] : ['nullable', 'integer', $valueRule];
                validator($data, $rules)->validate();
            }
            DB::table('product_product_attributes')->where('product_id', $variant->id)->whereIn('attribute_id', $attributeIds)->delete();
            $inserted = 0;
            foreach ($attributes as $attr) {
                $inserted += (int) $this->insertAttributePivot($variant, $attr, $data, now());
            }

            return $inserted;
        });
        $variant->unsetRelation('attributes')->unsetRelation('variantAttributes')->unsetRelation('attributeValues')->unsetRelation('variantAttributeValues');
        Product::flushAllProductCaches();

        Log::info('[SyncVariantVariationAttributesAction] synced', [
            'variant_id' => $variant->getKey(),
            'parent_id' => $parentProduct->getKey(),
            'attribute_ids' => $attributeIds,
            'inserted_count' => $inserted,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function insertAttributePivot(Product $variant, Attribute $attr, array $data, $now): bool
    {
        $valueData = $data['variation_attr_' . $attr->id] ?? null;
        $custom = $data['variation_custom_' . $attr->id] ?? null;
        $customStr = $custom !== null && $custom !== '' ? (string) $custom : null;
        $isStringType = in_array($attr->type, ['string', 'text'], true);
        
        $inserted = false;
        
        // Если это множественный атрибут (is_multiple = true)
        if ($attr->is_multiple) {
            // Преобразуем в массив, если это не массив
            $valueIds = is_array($valueData) ? $valueData : (empty($valueData) ? [] : [$valueData]);
            
            foreach ($valueIds as $valueId) {
                if ($valueId !== null && $valueId !== '') {
                    DB::table('product_product_attributes')->insert([
                        'product_id' => $variant->id,
                        'attribute_id' => $attr->id,
                        'attribute_value_id' => $valueId,
                        'custom_value' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $inserted = true;
                }
            }
            
            // Если есть кастомное значение — добавляем отдельно
            if ($attr->allow_custom_value && $customStr !== null) {
                DB::table('product_product_attributes')->insert([
                    'product_id' => $variant->id,
                    'attribute_id' => $attr->id,
                    'attribute_value_id' => null,
                    'custom_value' => $customStr,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted = true;
            }
            
            return $inserted;
        }
        
        // Старая логика для одиночных атрибутов
        if ($isStringType && $customStr !== null) {
            DB::table('product_product_attributes')->insert([
                'product_id' => $variant->id,
                'attribute_id' => $attr->id,
                'attribute_value_id' => null,
                'custom_value' => $customStr,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return true;
        }

        if ($valueData !== null && $valueData !== '') {
            DB::table('product_product_attributes')->insert([
                'product_id' => $variant->id,
                'attribute_id' => $attr->id,
                'attribute_value_id' => $valueData,
                'custom_value' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return true;
        }

        if ($attr->allow_custom_value && $customStr !== null) {
            DB::table('product_product_attributes')->insert([
                'product_id' => $variant->id,
                'attribute_id' => $attr->id,
                'attribute_value_id' => null,
                'custom_value' => $customStr,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function stripVariationFormKeys(array &$data): void
    {
        foreach (array_keys($data) as $key) {
            if (str_starts_with((string) $key, 'variation_attr_') || str_starts_with((string) $key, 'variation_custom_')) {
                unset($data[$key]);
            }
        }
    }
}
