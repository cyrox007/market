<?php

namespace App\Services\Shipping;

use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Settings\ProductStockSettings;
use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\WarehouseDeliveryRule;
use Illuminate\Support\Collection;

class WarehouseDeliveryOptionsService
{
    /**
     * @param array<int, array{product_id:int, quantity:int|float}> $items
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
    public function resolveForLocation(ShippingLocation $location, array $items = []): Collection
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

        if ($rules->isEmpty()) {
            return collect();
        }

        $fallbackDays = $location->getEffectiveDeliveryDays();
        $fallbackPrice = (float) ($location->getEffectiveDeliveryPrice() ?? 0);

        $options = $rules
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
            });

        if ($this->shouldFilterByWarehouseStock($items)) {
            $warehouseIds = $options->pluck('warehouse_id')->all();
            $normalizedItems = collect($items)
                ->filter(fn (array $item) => isset($item['product_id'], $item['quantity']) && (float) $item['quantity'] > 0)
                ->map(fn (array $item) => [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (float) $item['quantity'],
                ])
                ->values();

            if ($normalizedItems->isNotEmpty()) {
                $productIds = $normalizedItems->pluck('product_id')->unique()->all();
                $stockRows = ProductWarehouseStock::query()
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->whereIn('product_id', $productIds)
                    ->get(['warehouse_id', 'product_id', 'quantity'])
                    ->groupBy('warehouse_id');

                $options = $options->filter(function (array $option) use ($normalizedItems, $stockRows): bool {
                    $stocks = $stockRows->get($option['warehouse_id'], collect())
                        ->keyBy('product_id');

                    foreach ($normalizedItems as $item) {
                        $available = (float) ($stocks->get($item['product_id'])?->quantity ?? 0);
                        if ($available < $item['quantity']) {
                            return false;
                        }
                    }

                    return true;
                });
            }
        }

        return $options
            ->sortBy([
                fn (array $option) => -$option['priority'],
                fn (array $option) => $option['delivery_price'],
                fn (array $option) => $option['warehouse_id'],
            ])
            ->values();
    }

    public function hasRulesForLocation(ShippingLocation $location): bool
    {
        return WarehouseDeliveryRule::query()
            ->active()
            ->whereIn('shipping_location_id', $location->getAncestorsIds())
            ->whereHas('warehouse', fn ($query) => $query->where('is_active', true))
            ->exists();
    }

    private function shouldFilterByWarehouseStock(array $items): bool
    {
        if ($items === []) {
            return false;
        }

        return (bool) ProductStockSettings::getInstance()->warehouse_accounting_enabled;
    }
}
