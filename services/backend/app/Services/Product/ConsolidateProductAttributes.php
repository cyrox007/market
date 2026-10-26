<?php

namespace App\Services\Product;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Retire the legacy pivot without deleting or silently overwriting operator data. */
class ConsolidateProductAttributes
{
    /** Read-only preflight; report all conflict counts but bound the diagnostic sample. */
    public function audit(): array
    {
        $report = ['legacy_rows' => 0, 'rows_to_copy' => 0, 'conflicts_count' => 0, 'conflicts' => []];
        $this->eachProduct(function (Collection $legacy, Collection $current, int $productId) use (&$report): void {
            $report['legacy_rows'] += $legacy->count();
            foreach ($legacy->groupBy('attribute_id') as $attributeId => $rows) {
                $existing = $current->where('attribute_id', $attributeId);
                if ($existing->isEmpty()) {
                    $report['rows_to_copy'] += count($this->values($rows));
                } elseif ($this->values($existing) !== $this->values($rows)) {
                    $report['conflicts_count']++;
                    if (count($report['conflicts']) < 50) {
                        $report['conflicts'][] = ['product_id' => $productId, 'attribute_id' => (int) $attributeId];
                    }
                }
            }
        });

        return $report;
    }

    public function execute(): int
    {
        return DB::transaction(function (): int {
            // Preflight the entire source before writing even one row.
            $report = $this->audit();
            if ($report['conflicts_count'] > 0) {
                $first = $report['conflicts'][0];
                throw new RuntimeException("Conflicting product attributes: product_id={$first['product_id']}, attribute_id={$first['attribute_id']}; total={$report['conflicts_count']}. Run catalog:attribute-storage-audit and resolve the two stored value sets before retrying; neither source was overwritten.");
            }
            $inserted = 0;
            $this->eachProduct(function (Collection $legacy, Collection $current, int $productId) use (&$inserted): void {
                DB::table('products')->where('id', $productId)->lockForUpdate()->first();
                $current = DB::table('product_product_attributes')->where('product_id', $productId)->get();
                foreach ($legacy->groupBy('attribute_id') as $attributeId => $rows) {
                    if ($current->contains('attribute_id', $attributeId)) {
                        if ($this->values($current->where('attribute_id', $attributeId)) !== $this->values($rows)) {
                            throw new RuntimeException("Attributes changed during consolidation: product_id={$productId}, attribute_id={$attributeId}. Transaction rolled back.");
                        }

                        continue;
                    }
                    foreach ($rows->unique(fn ($row) => json_encode([$row->attribute_value_id, $row->custom_value])) as $row) {
                        DB::table('product_product_attributes')->insert([
                            'product_id' => $productId, 'attribute_id' => $attributeId,
                            'attribute_value_id' => $row->attribute_value_id, 'custom_value' => $row->custom_value,
                            'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                        ]);
                        $inserted++;
                    }
                }
            });

            return $inserted;
        });
    }

    private function eachProduct(callable $callback): void
    {
        DB::table('product_variant_attributes')->select('product_id')->distinct()->orderBy('product_id')
            ->chunkById(200, function (Collection $products) use ($callback): void {
                $ids = $products->pluck('product_id');
                $legacy = DB::table('product_variant_attributes')->whereIn('product_id', $ids)->get()->groupBy('product_id');
                $current = DB::table('product_product_attributes')->whereIn('product_id', $ids)->get()->groupBy('product_id');
                foreach ($legacy as $id => $rows) {
                    $callback($rows, $current->get($id, collect()), (int) $id);
                }
            }, 'product_id');
    }

    private function values(Collection $rows): array
    {
        return $rows->map(fn ($row) => json_encode([
            $row->attribute_value_id === null ? null : (int) $row->attribute_value_id,
            $row->custom_value === null ? null : (string) $row->custom_value,
        ]))->unique()->sort()->values()->all();
    }
}
