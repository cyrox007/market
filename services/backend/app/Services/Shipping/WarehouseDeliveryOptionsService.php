<?php

namespace App\Services\Shipping;

use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\WarehouseDeliveryRule;
use Illuminate\Support\Collection;

class WarehouseDeliveryOptionsService
{
    /**
     * Resolve all active warehouse delivery options for a destination.
     *
     * Rules assigned to the exact location win over rules inherited from its
     * parent locations. Existing warehouse/location links with null price or
     * delivery days remain compatible by falling back to location defaults.
     *
     * @return Collection<int, array{
     *     warehouse_id:int,
     *     warehouse_name:string,
     *     location_id:int,
     *     inherited:bool,
     *     delivery_price:float,
     *     delivery_days_min:?int,
     *     delivery_days_max:?int,
     *     priority:int
     * }>
     */
    public function resolveForLocation(ShippingLocation $location): Collection
    {
        $ancestorIds = $location->getAncestorsIds();
        $distance = array_flip($ancestorIds);

        $rules = WarehouseDeliveryRule::query()
            ->active()
            ->whereIn('shipping_location_id', $ancestorIds)
            ->whereHas('warehouse', fn ($query) => $query->where('is_active', true))
            ->with('warehouse')
            ->get()
            ->sortBy([
                fn (WarehouseDeliveryRule $rule) => $distance[$rule->shipping_location_id] ?? PHP_INT_MAX,
                fn (WarehouseDeliveryRule $rule) => -$rule->priority,
                fn (WarehouseDeliveryRule $rule) => $rule->warehouse_id,
            ])
            ->groupBy('warehouse_id')
            ->map(fn (Collection $warehouseRules) => $warehouseRules->first())
            ->filter();

        $fallbackDays = $location->getEffectiveDeliveryDays();
        $fallbackPrice = (float) ($location->getEffectiveDeliveryPrice() ?? 0);

        return $rules
            ->map(function (WarehouseDeliveryRule $rule) use ($location, $fallbackDays, $fallbackPrice): array {
                return [
                    'warehouse_id' => (int) $rule->warehouse_id,
                    'warehouse_name' => (string) ($rule->warehouse?->name ?? ''),
                    'location_id' => (int) $rule->shipping_location_id,
                    'inherited' => (int) $rule->shipping_location_id !== (int) $location->id,
                    'delivery_price' => $rule->delivery_price !== null
                        ? (float) $rule->delivery_price
                        : $fallbackPrice,
                    'delivery_days_min' => $rule->delivery_days_min ?? ($fallbackDays['min'] ?? null),
                    'delivery_days_max' => $rule->delivery_days_max ?? ($fallbackDays['max'] ?? null),
                    'priority' => (int) $rule->priority,
                ];
            })
            ->sortBy([
                fn (array $option) => -$option['priority'],
                fn (array $option) => $option['delivery_price'],
                fn (array $option) => $option['warehouse_id'],
            ])
            ->values();
    }
}
