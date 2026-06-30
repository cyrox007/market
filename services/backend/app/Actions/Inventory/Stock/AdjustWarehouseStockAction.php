<?php

namespace App\Actions\Inventory\Stock;

use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;

class AdjustWarehouseStockAction
{
    public function __construct(
        protected ResolveWarehouseStockRowAction $resolveWarehouseStockRowAction,
    ) {
    }

    public function decrease(Product $product, int $quantity, ?int $shippingLocationId): ?int
    {
        return $this->adjust($product, $quantity, $shippingLocationId, 'decrease');
    }

    public function increase(Product $product, int $quantity, ?int $shippingLocationId): ?int
    {
        return $this->adjust($product, $quantity, $shippingLocationId, 'increase');
    }

    private function adjust(Product $product, int $quantity, ?int $shippingLocationId, string $mode): ?int
    {
        $settings = ProductStockSettings::getInstance();
        if (! $settings->warehouse_accounting_enabled || $quantity <= 0) {
            return null;
        }

        $stockRow = $this->resolveWarehouseStockRowAction->execute(
            $product,
            $shippingLocationId,
            (bool) $settings->fallback_to_first_warehouse
        );
        if ($stockRow === null) {
            return null;
        }

        if ($mode === 'decrease') {
            $stockRow->quantity = max(0, (float) $stockRow->quantity - $quantity);
        } else {
            $stockRow->quantity = (float) $stockRow->quantity + $quantity;
        }
        $stockRow->save();

        return (int) $stockRow->warehouse_id;
    }
}

