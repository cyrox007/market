<?php

namespace App\Services\Inventory;

use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use App\Models\Settings\ProductStockSettings;
use App\Services\Shipping\WarehouseDistance;

class WarehousePriorityResolver
{
    public function resolve(Product $product, ?ShippingLocation $location): array
    {
        $point = request()->attributes->get('warehouse_destination')['point'] ?? null;
        $stocks = ProductStockSettings::getInstance()->warehouse_accounting_enabled
            ? app(WarehouseStockResolver::class)->eligibleStocks($product, $location)->map(function ($stock) use ($point): array {
                return ['warehouse_id' => $stock->warehouse_id, 'warehouse_name' => $stock->warehouse->name,
                    'quantity' => (float) $stock->quantity,
                    'distance_km' => $point ? app(WarehouseDistance::class)->kilometres($stock->warehouse, $point['latitude'], $point['longitude']) : null];
            })->sort(fn ($a, $b) => [$a['distance_km'] === null, $a['distance_km'] ?? INF, $a['warehouse_id']]
                <=> [$b['distance_km'] === null, $b['distance_km'] ?? INF, $b['warehouse_id']])->values()
            : collect();
        $nearest = $stocks->first(fn ($stock) => $stock['quantity'] > 0 && $stock['distance_km'] !== null);

        return ['nearest_stock' => $nearest['quantity'] ?? null, 'nearest_warehouse_id' => $nearest['warehouse_id'] ?? null,
            'nearest_warehouse_name' => $nearest['warehouse_name'] ?? null, 'nearest_distance_km' => $nearest['distance_km'] ?? null,
            'stocks' => $stocks->all()];
    }
}
