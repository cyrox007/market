<?php

namespace App\Services\Shipping;

use App\Models\Inventory\Warehouse;
use App\Models\Page\Store;

class PickupReadiness
{
    public function ready(Warehouse $warehouse, ?Store $store = null): bool
    {
        if (! $warehouse->is_active || $warehouse->source_type !== 'physical' || $warehouse->stock_mode !== 'quantity'
            || blank($warehouse->physicalSite?->address ?? $warehouse->address)) {
            return false;
        }

        if ($store) {
            return $store->is_active && $store->pickup_enabled && (int) $store->warehouse_id === (int) $warehouse->id
                && $store->physical_site_id !== null && (int) $store->physical_site_id === (int) $warehouse->physical_site_id;
        }

        return (bool) $warehouse->pickup_enabled;
    }
}
