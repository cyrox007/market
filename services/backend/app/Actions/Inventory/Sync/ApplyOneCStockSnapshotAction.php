<?php

declare(strict_types=1);

namespace App\Actions\Inventory\Sync;

use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;

class ApplyOneCStockSnapshotAction
{
    /**
     * @param list<array<string,mixed>> $rows
     * @return array{warehouse_rows:int,total_stock:float}
     */
    public function execute(Product $product, array $rows, ?float $aggregate = null): array
    {
        return DB::transaction(function () use ($product, $rows, $aggregate): array {
            $warehouseRows = 0;
            $seenWarehouseIds = [];

            foreach ($rows as $row) {
                $stockId = trim((string) ($row['stockId']
                    ?? $row['stock_id']
                    ?? $row['warehouseId']
                    ?? $row['warehouse_id']
                    ?? ''));

                if ($stockId === '') {
                    continue;
                }

                $quantity = $this->adaptQuantity(
                    $row['count'] ?? $row['quantity'] ?? $row['stock'] ?? 0
                );

                $warehouse = Warehouse::updateOrCreate(
                    ['external_id' => $stockId],
                    [
                        'name' => (string) ($row['stockName']
                            ?? $row['stock_name']
                            ?? $row['warehouseName']
                            ?? $row['warehouse_name']
                            ?? $stockId),
                        'is_active' => true,
                    ]
                );

                ProductWarehouseStock::withoutEvents(function () use ($product, $warehouse, $quantity): void {
                    ProductWarehouseStock::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'warehouse_id' => $warehouse->id,
                        ],
                        ['quantity' => $quantity]
                    );
                });

                $seenWarehouseIds[] = (int) $warehouse->id;
                $warehouseRows++;
            }

            $staleRows = ProductWarehouseStock::query()
                ->where('product_id', $product->id)
                ->whereHas('warehouse', fn ($query) => $query
                    ->whereNotNull('external_id')
                    ->where('external_id', '!=', ''));

            if ($seenWarehouseIds !== []) {
                $staleRows->whereNotIn('warehouse_id', array_values(array_unique($seenWarehouseIds)));
            }

            ProductWarehouseStock::withoutEvents(function () use ($staleRows): void {
                $staleRows->update(['quantity' => 0]);
            });

            if ($warehouseRows > 0) {
                $totalStock = (float) ProductWarehouseStock::query()
                    ->where('product_id', $product->id)
                    ->sum('quantity');
            } elseif ($aggregate !== null) {
                $totalStock = $this->adaptQuantity($aggregate);
            } else {
                $totalStock = 0.0;
            }

            $product->stock = $totalStock;
            $product->saveQuietly();
            $product->flushCache();

            return [
                'warehouse_rows' => $warehouseRows,
                'total_stock' => $totalStock,
            ];
        });
    }

    private function adaptQuantity(mixed $raw): float
    {
        $numeric = is_numeric($raw) ? (float) $raw : 0.0;

        return max(0.0, (float) round($numeric, 0, PHP_ROUND_HALF_UP));
    }
}
