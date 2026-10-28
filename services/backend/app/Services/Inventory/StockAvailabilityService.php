<?php

namespace App\Services\Inventory;

use App\Models\Product\Product;
use Illuminate\Support\Facades\Log;

class StockAvailabilityService
{
    public function __construct(
        protected WarehouseStockResolver $warehouseStockResolver,
    ) {
    }

    public function resolveAvailableStock(Product $product): ?int
    {
        $composition = app(\App\Services\Product\CartFulfillment::class)->composition($product);
        if ($composition !== null) {
            $capacity = null;
            foreach ($composition['items'] as $line) {
                $sku = Product::findOrFail($line['product_id']);
                if ($this->warehouseStockResolver->availableToOrder($sku, null)) { continue; }
                $sets = (int) floor(($this->warehouseStockResolver->resolveForProduct($sku, null) ?? 0) / $line['quantity']);
                $capacity = $capacity === null ? $sets : min($capacity, $sets);
            }
            return $capacity;
        }
        $location = null;
        if ($this->warehouseStockResolver->availableToOrder($product, null)) {
            return null;
        }
        $resolved = max(0, (int) round($this->warehouseStockResolver->resolveForProduct($product, $location) ?? 0));
        Log::info('Resolved available stock for product', [
            'product_id' => $product->id,
            'resolved_stock' => $resolved,
        ]);

        return $resolved;
    }
}
