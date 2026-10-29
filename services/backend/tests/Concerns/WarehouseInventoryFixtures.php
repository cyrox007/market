<?php

namespace Tests\Concerns;

use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;

/** Explicit warehouse inventory for tests of cart/auth/pricing, not a production fallback. */
trait WarehouseInventoryFixtures
{
    protected Warehouse $inventoryWarehouse;

    protected function configureWarehouseInventoryFixtures(): void
    {
        $this->inventoryWarehouse = Warehouse::create(['name' => 'Test inventory', 'address' => 'Test pickup address', 'source_type' => 'physical', 'stock_mode' => 'quantity', 'is_active' => true, 'pickup_enabled' => true]);
        Product::created(function (Product $product): void {
            $quantity = $product->getAttributes()['stock'] ?? 100;
            $this->inventoryWarehouse->productStocks()->create(['product_id' => $product->id, 'quantity' => $quantity]);
            if ($product->backorder) {
                $supplier = Warehouse::firstOrCreate(['name' => 'Test supplier'], ['source_type' => 'supplier', 'stock_mode' => 'availability', 'is_active' => true]);
                $supplier->productAvailabilities()->create(['product_id' => $product->id, 'available_to_order' => true]);
            }
        });
    }
}
