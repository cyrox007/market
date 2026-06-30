<?php

namespace App\Actions\Inventory\Stock;

use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;

class ResolveWarehouseStockRowAction
{
    public function execute(Product $product, ?int $shippingLocationId, bool $allowFallback): ?ProductWarehouseStock
    {
        if ($shippingLocationId !== null) {
            $location = ShippingLocation::query()->find($shippingLocationId);
            if ($location !== null) {
                $locationIds = array_unique(array_merge([$location->id], $location->getAncestorsIds()));
                $match = $product->warehouseStocks()
                    ->whereHas('warehouse.shippingLocations', function ($q) use ($locationIds) {
                        $q->whereIn('shipping_locations.id', $locationIds);
                    })
                    ->orderBy('warehouse_id')
                    ->first();
                if ($match !== null) {
                    return $match;
                }
            }
        }

        if (! $allowFallback) {
            return null;
        }

        return $product->warehouseStocks()->orderBy('warehouse_id')->first();
    }
}

