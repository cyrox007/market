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
        $changed = DB::transaction(function () use ($product, $rows, $attributeIds): bool {
            DB::table('products')->where('id', $product->id)->lockForUpdate()->first();
            $delete = DB::table('product_product_attributes')->where('product_id', $product->id);
            $attributeIds !== null
                ? $delete->whereIn('attribute_id', $attributeIds)
                : $delete->whereNotIn('attribute_id', Attribute::hiddenIds())
                    ->whereNotIn('attribute_id', Attribute::query()->select('id')->where('slug', Attribute::SLUG_MANUFACTURER));

            $selectedIds = array_unique(array_map('intval', array_column($rows, 'attribute_id')));
            $attributes = Attribute::query()->whereKey($selectedIds)->get()->keyBy('id');
            $selectedValues = collect($rows)->flatMap(fn (array $row) => (array) ($row['attribute_value_id'] ?? []))
                ->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->all();
            $valuesByAttribute = AttributeValue::query()->whereKey($selectedValues)
                ->get(['id', 'attribute_id'])->groupBy('attribute_id');

            $insert = [];
            foreach (array_values($rows) as $position => $row) {
                $attributeId = isset($row['attribute_id']) ? (int) $row['attribute_id'] : 0;
                if ($attributeId <= 0) {
                    continue;
                }
                if ($attributeIds !== null && ! in_array($attributeId, $attributeIds, true)) {
                    continue;
                }

                $attribute = $attributes->get($attributeId);
                if (! $attribute || $attribute->slug === Attribute::SLUG_MANUFACTURER) {
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
                    $validValueIds = $valuesByAttribute->get($attributeId)?->pluck('id')
                        ->map(fn ($id) => (int) $id)->intersect($valueIds)->all() ?? [];

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

            $insert = collect($insert)->unique(fn (array $row) => json_encode([
                $row['attribute_id'], $row['attribute_value_id'], $row['custom_value'],
            ]))->values()->all();
            $signature = static fn ($row): array => [
                (int) $row['attribute_id'],
                isset($row['attribute_value_id']) ? (int) $row['attribute_value_id'] : null,
                $row['custom_value'],
                (int) $row['sort_order'],
            ];
            $current = $delete->get(['attribute_id', 'attribute_value_id', 'custom_value', 'sort_order'])
                ->map(fn ($row) => $signature((array) $row))->sort()->values()->all();
            if ($current === collect($insert)->map($signature)->sort()->values()->all()) {
                return false;
            }

            $delete->delete();
            if ($insert !== []) {
                DB::table('product_product_attributes')->insert($insert);
            }

            return true;
        });

        if (! $changed) {
            return;
        }

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
