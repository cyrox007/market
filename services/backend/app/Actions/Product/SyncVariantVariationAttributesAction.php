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
        $attributes = $this->getVariationAttributesForProductAction->execute($parentProduct, $forcedAttributeIds);

        if ($attributes->isEmpty()) {
            Log::debug('[SyncVariantVariationAttributesAction] no attributes to sync', [
                'variant_id' => $variant->getKey(),
                'parent_id' => $parentProduct->getKey(),
            ]);

            return;
        }

        $attributeIds = $attributes->pluck('id')->all();

        DB::table('product_variant_attributes')
            ->where('product_id', $variant->id)
            ->whereIn('attribute_id', $attributeIds)
            ->delete();

        $inserted = 0;
        $now = now();

        foreach ($attributes as $attr) {
            if ($this->insertAttributePivot($variant, $attr, $data, $now)) {
                $inserted++;
            }
        }

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
        $valueId = $data['variation_attr_' . $attr->id] ?? null;
        $custom = $data['variation_custom_' . $attr->id] ?? null;
        $customStr = $custom !== null && $custom !== '' ? (string) $custom : null;
        $isStringType = in_array($attr->type, ['string', 'text'], true);

        if ($isStringType && $customStr !== null) {
            DB::table('product_variant_attributes')->insert([
                'product_id' => $variant->id,
                'attribute_id' => $attr->id,
                'attribute_value_id' => null,
                'custom_value' => $customStr,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return true;
        }

        if ($valueId !== null && $valueId !== '') {
            DB::table('product_variant_attributes')->insert([
                'product_id' => $variant->id,
                'attribute_id' => $attr->id,
                'attribute_value_id' => $valueId,
                'custom_value' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return true;
        }

        if ($attr->allow_custom_value && $customStr !== null) {
            DB::table('product_variant_attributes')->insert([
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
