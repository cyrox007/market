<?php

namespace App\Services\Product;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;

class ProductAttributeSyncService
{
    /**
     * Persist operator-managed (non-variation pivot) product attributes.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function sync(Product $product, array $rows, ?array $attributeIds = null): void
    {
        DB::transaction(function () use ($product, $rows, $attributeIds): void {
            DB::table('products')->where('id', $product->id)->lockForUpdate()->first();
            $delete = DB::table('product_product_attributes')->where('product_id', $product->id);
            if ($attributeIds !== null) {
                $delete->whereIn('attribute_id', $attributeIds);
            }
            $delete->delete();

            $insert = [];
            foreach (array_values($rows) as $position => $row) {
                $attributeId = isset($row['attribute_id']) ? (int) $row['attribute_id'] : 0;
                if ($attributeId <= 0) {
                    continue;
                }
                if ($attributeIds !== null && ! in_array($attributeId, $attributeIds, true)) {
                    continue;
                }

                $attribute = Attribute::query()->find($attributeId);
                if (! $attribute) {
                    continue;
                }

                $valueIds = $row['attribute_value_id'] ?? null;
                if (! is_array($valueIds)) {
                    $valueIds = $valueIds !== null && $valueIds !== '' ? [$valueIds] : [];
                }

                $valueIds = array_values(array_unique(array_filter(array_map(
                    fn ($value) => is_numeric($value) ? (int) $value : 0,
                    $valueIds,
                ))));

                if ($valueIds !== []) {
                    $validValueIds = AttributeValue::query()
                        ->where('attribute_id', $attributeId)
                        ->whereIn('id', $valueIds)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    foreach ($validValueIds as $valueId) {
                        $insert[] = $this->row($product->id, $attributeId, $valueId, null, $position);
                    }

                    if (! $attribute->is_multiple) {
                        continue;
                    }
                }

                $customValue = trim((string) ($row['custom_value'] ?? ''));
                if ($customValue !== '' && $attribute->allow_custom_value) {
                    $insert[] = $this->row($product->id, $attributeId, null, $customValue, $position);
                }
            }

            if ($insert !== []) {
                DB::table('product_product_attributes')->insert(collect($insert)->unique(fn (array $row) => json_encode([
                    $row['attribute_id'], $row['attribute_value_id'], $row['custom_value'],
                ]))->values()->all());
            }
        });

        // Pivots are saved after the product event; invalidate the final persisted attribute state.
        $product->unsetRelation('attributes')->unsetRelation('variantAttributes')->unsetRelation('attributeValues')->unsetRelation('variantAttributeValues');
        Product::flushAllProductCaches();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $productId, int $attributeId, ?int $attributeValueId, ?string $customValue, int $position): array
    {
        return [
            'product_id' => $productId,
            'attribute_id' => $attributeId,
            'attribute_value_id' => $attributeValueId,
            'custom_value' => $customValue,
            'sort_order' => $position,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
