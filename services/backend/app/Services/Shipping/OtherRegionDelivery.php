<?php

namespace App\Services\Shipping;

use App\Models\Inventory\Warehouse;
use Illuminate\Support\Collection;

/** Доставка со склада другого региона со стоимостью и сроком «уточняется». */
class OtherRegionDelivery
{
    /** Ближайший склад вне зоны покупателя, где свободен весь заказ; остатки в $warehouses уже за вычетом удержаний в корзинах. */
    public function option(Collection $warehouses, array $quantities, array $destination, ?array $point): ?array
    {
        $nearest = $warehouses
            ->filter(fn (Warehouse $warehouse) => $warehouse->is_active && $warehouse->stock_mode === 'quantity'
                && app(WarehouseCoverage::class)->firstProfile($warehouse, $destination) === null
                && collect($quantities)->every(fn ($quantity, $id) => ($warehouse->productStocks->firstWhere('product_id', $id)?->quantity ?? 0) >= $quantity))
            ->map(fn (Warehouse $warehouse) => [$warehouse, $point ? app(WarehouseDistance::class)->kilometres($warehouse, $point['latitude'], $point['longitude']) : null])
            ->sortBy(fn (array $candidate) => [$candidate[1] === null, $candidate[1] ?? 0, $candidate[0]->id])
            ->first();
        if (! $nearest) {
            return null;
        }
        [$warehouse, $distance] = $nearest;

        return [
            'mode' => 'other_region',
            'warehouse_id' => $warehouse->id, 'warehouse_name' => $warehouse->name,
            'warehouse_external_id' => $warehouse->external_id, 'stock_mode' => $warehouse->stock_mode,
            'profile_id' => null, 'profile_name' => null,
            'warehouse_fingerprint' => hash('sha256', json_encode([$warehouse->getAttributes(), $warehouse->physicalSite?->getAttributes()])),
            'delivery_cost' => null, 'transport_cost' => null, 'handling_cost' => 0.0, 'assembly_cost' => 0.0,
            'additional_services_cost' => 0.0, 'additional_services' => [], 'assembly_days' => null, 'requires_assembly' => false,
            'service_options' => ['assembly' => ['available' => false, 'required' => false, 'price' => null, 'days' => null], 'handling' => [], 'additional' => []],
            'total_fulfillment_cost' => null, 'base_price' => null, 'free_delivery_threshold' => null,
            'transit_days_min' => null, 'transit_days_max' => null, 'processing_days_min' => null, 'processing_days_max' => null,
            'delivery_days_min' => null, 'delivery_days_max' => null,
            'delivery' => ['days_min' => null, 'days_max' => null, 'date_min' => null, 'date_max' => null,
                'day_type' => 'calendar', 'timezone' => 'Europe/Moscow', 'label' => 'Срок поставки уточняется', 'requires_confirmation' => true],
            'distance_km' => $distance, 'distance_type' => 'straight_line',
            'items' => collect($quantities)->map(fn ($quantity, $id) => ['product_id' => (int) $id, 'quantity' => $quantity, 'preorder_quantity' => 0])->values()->all(),
            'contains_backorder' => false, 'backorder_items' => [],
            'delivery_estimate_unavailable' => true,
        ];
    }
}
