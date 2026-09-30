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
    public function sync(Product $product, array $rows): void
    {
        DB::transaction(function () use ($product, $rows): void {
            DB::table('product_product_attributes')
                ->where('product_id', $product->id)
                ->delete();

            $insert = [];
            foreach ($rows as $row) {
                $attributeId = isset($row['attribute_id']) ? (int) $row['attribute_id'] : 0;
                if ($attributeId <= 0) {
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
                        $insert[] = $this->row($product->id, $attributeId, $valueId, null);
                    }

                    continue;
                }

                $customValue = trim((string) ($row['custom_value'] ?? ''));
                if ($customValue !== '' && $attribute->allow_custom_value) {
                    $insert[] = $this->row($product->id, $attributeId, null, $customValue);
                }
            }

            if ($insert !== []) {
                DB::table('product_product_attributes')->insert($insert);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $productId, int $attributeId, ?int $attributeValueId, ?string $customValue): array
    {
        return [
            'product_id' => $productId,
            'attribute_id' => $attributeId,
            'attribute_value_id' => $attributeValueId,
            'custom_value' => $customValue,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
