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
    public function execute(Product $product, array $rows): array
    {
        return DB::transaction(function () use ($product, $rows): array {
            $seenWarehouseIds = [];
            $warehouseRows = 0;

            foreach ($rows as $row) {
                $stockExternalId = trim((string) (
                    $row['stockId']
                    ?? $row['stock_id']
                    ?? $row['warehouseId']
                    ?? $row['warehouse_id']
                    ?? ''
                ));

                if ($stockExternalId === '') {
                    continue;
                }

                $quantity = $this->normalizeQuantity(
                    $row['count']
                    ?? $row['quantity']
                    ?? $row['stock']
                    ?? 0
                );

                $warehouse = Warehouse::query()->updateOrCreate(
                    ['external_id' => $stockExternalId],
                    [
                        'name' => (string) (
                            $row['stockName']
                            ?? $row['stock_name']
                            ?? $row['warehouseName']
                            ?? $row['warehouse_name']
                            ?? $stockExternalId
                        ),
                        'is_active' => true,
                    ]
                );

                ProductWarehouseStock::withoutEvents(function () use ($product, $warehouse, $quantity): void {
                    ProductWarehouseStock::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'warehouse_id' => $warehouse->id,
                        ],
                        [
                            'quantity' => $quantity,
                        ]
                    );
                });

                $seenWarehouseIds[] = (int) $warehouse->id;
                $warehouseRows++;
            }

            // Ответ endpoint /stocks — снимок остатков товара.
            // Если склад исчез из ответа, старый остаток по этому складу должен стать нулём.
            $staleQuery = ProductWarehouseStock::query()
                ->where('product_id', $product->id)
                ->whereHas('warehouse', fn ($query) => $query
                    ->whereNotNull('external_id')
                    ->where('external_id', '!=', ''));

            if ($seenWarehouseIds !== []) {
                $staleQuery->whereNotIn(
                    'warehouse_id',
                    array_values(array_unique($seenWarehouseIds))
                );
            }

            ProductWarehouseStock::withoutEvents(
                fn () => $staleQuery->update(['quantity' => 0])
            );

            $totalStock = (float) ProductWarehouseStock::query()
                ->where('product_id', $product->id)
                ->sum('quantity');

            $product->stock = $totalStock;
            $product->saveQuietly();
            $product->flushCache();

            return [
                'warehouse_rows' => $warehouseRows,
                'total_stock' => $totalStock,
            ];
        });
    }

    private function normalizeQuantity(mixed $raw): float
    {
        $numeric = is_numeric($raw) ? (float) $raw : 0.0;

        return max(0.0, (float) round($numeric, 0, PHP_ROUND_HALF_UP));
    }
}
