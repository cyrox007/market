<?php

namespace App\Services\Shipping;

use App\Models\Inventory\Warehouse;
use App\Models\Page\Store;

class WarehousePickup
{
    public function select(int $warehouseId, ?int $storeId, array $lines): array
    {
        $lines = app(FulfillmentLines::class)->resolve($lines);
        $option = collect($this->options($lines))->first(fn ($row) => $row['warehouse_id'] === $warehouseId && $row['store_id'] === $storeId);
        if (! $option) {
            throw \Illuminate\Validation\ValidationException::withMessages(['warehouse_id' => 'Точка самовывоза недоступна или недостаточно остатков.']);
        }

        return [...$option, 'mode' => 'pickup', 'stock_mode' => 'quantity', 'profile_id' => null,
            'delivery_cost' => 0.0, 'assembly_cost' => 0.0, 'base_price' => 0.0,
            'delivery_days_min' => $option['ready']['days_min'], 'delivery_days_max' => $option['ready']['days_max'],
            'items' => collect($lines)->groupBy('product_id')->map(fn ($rows, $id) => ['product_id' => (int) $id, 'quantity' => $rows->sum('quantity')])->values()->all()];
    }

    public function options(array $lines, ?array $point = null): array
    {
        $quantities = collect($lines)->groupBy('product_id')->map(fn ($rows) => $rows->sum('quantity'));
        $warehouses = Warehouse::where('is_active', true)->where('source_type', 'physical')->where('stock_mode', 'quantity')
            ->where(fn ($q) => $q->where('pickup_enabled', true)->orWhereHas('stores', fn ($s) => $s->where('is_active', true)->where('pickup_enabled', true)))
            ->with(['physicalSite', 'stores' => fn ($s) => $s->where('is_active', true)->where('pickup_enabled', true),
                'productStocks' => fn ($s) => $s->whereIn('product_id', $quantities->keys())])->get();
        $options = [];
        foreach ($warehouses as $warehouse) {
            $stocks = $warehouse->productStocks->keyBy('product_id');
            if ($quantities->contains(fn ($quantity, $id) => ($stocks[$id]->quantity ?? 0) < $quantity)) {
                continue;
            }
            $stores = $warehouse->stores->filter(fn (Store $store) => $store->physical_site_id && (int) $store->physical_site_id === (int) $warehouse->physical_site_id);
            $points = $stores->isNotEmpty() ? $stores : ($warehouse->pickup_enabled ? collect([null]) : collect());
            foreach ($points as $store) {
                $options[] = ['warehouse_id' => $warehouse->id, 'store_id' => $store?->id,
                    'name' => $store?->name ?? $warehouse->name, 'address' => $warehouse->physicalSite?->address ?? $warehouse->address,
                    'hours' => $store?->hours, 'opening_hours' => $store?->opening_hours,
                    'timezone' => $store?->timezone ?? 'Europe/Moscow',
                    'distance_km' => $point ? app(WarehouseDistance::class)->kilometres($warehouse, $point['latitude'], $point['longitude']) : null,
                    'pickup_cost' => 0.0, 'ready' => app(FulfillmentDates::class)->range((int) $warehouse->processing_days_min, max((int) $warehouse->processing_days_min, (int) $warehouse->processing_days_max)),
                    'items' => $quantities->map(fn ($quantity, $id) => ['product_id' => (int) $id, 'requested_quantity' => $quantity, 'available_quantity' => (float) $stocks[$id]->quantity])->values()->all()];
            }
        }

        return collect($options)->sort(fn ($a, $b) => [$a['distance_km'] === null, $a['distance_km'] ?? INF, $a['warehouse_id'], $a['store_id'] ?? 0]
            <=> [$b['distance_km'] === null, $b['distance_km'] ?? INF, $b['warehouse_id'], $b['store_id'] ?? 0])->values()->all();
    }
}
