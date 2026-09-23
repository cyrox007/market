<?php

namespace App\Services\Shipping;

use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Settings\ProductStockSettings;
use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\WarehouseDeliveryMethod;
use App\Models\Shipping\WarehouseDeliveryZone;
use Illuminate\Support\Collection;

class WarehouseDeliveryOptionsService
{
    /**
     * Возвращает конкретные варианты доставки:
     * склад + способ доставки + перевозчик + тариф + SLA.
     *
     * @param array<int, array{product_id:int, quantity:int|float}> $items
     * @return Collection<int, array<string, mixed>>
     */
    public function resolveForLocation(
        ShippingLocation $location,
        array $items = [],
        float $orderAmount = 0.0
    ): Collection {
        $ancestorIds = $location->getAncestorsIds();
        $distance = array_flip($ancestorIds);

        $methods = WarehouseDeliveryMethod::query()
            ->active()
            ->whereHas('warehouse', fn ($query) => $query->where('is_active', true))
            ->whereHas('shippingMethod', fn ($query) => $query->where('is_active', true))
            ->whereHas('shippingMethod.carrier', fn ($query) => $query->where('is_active', true))
            ->whereHas('zones', function ($query) use ($ancestorIds) {
                $query->active()->whereIn('shipping_location_id', $ancestorIds);
            })
            ->with([
                'warehouse',
                'shippingMethod.carrier',
                'zones' => function ($query) use ($ancestorIds) {
                    $query->active()->whereIn('shipping_location_id', $ancestorIds);
                },
            ])
            ->get();

        $options = $methods
            ->map(fn (WarehouseDeliveryMethod $method) => $this->buildOption(
                $method,
                $location,
                $distance,
                $orderAmount
            ))
            ->filter()
            ->values();

        $options = $this->filterByWarehouseStock($options, $items);

        return $options
            ->sortBy([
                fn (array $option) => -$option['method_priority'],
                fn (array $option) => -$option['zone_priority'],
                fn (array $option) => $option['delivery_price'],
                fn (array $option) => $option['warehouse_id'],
                fn (array $option) => $option['shipping_method_id'],
            ])
            ->values();
    }

    public function hasConfiguredMethodsForLocation(ShippingLocation $location): bool
    {
        $ancestorIds = $location->getAncestorsIds();

        return WarehouseDeliveryMethod::query()
            ->active()
            ->whereHas('warehouse', fn ($query) => $query->where('is_active', true))
            ->whereHas('shippingMethod', fn ($query) => $query->where('is_active', true))
            ->whereHas('shippingMethod.carrier', fn ($query) => $query->where('is_active', true))
            ->whereHas('zones', function ($query) use ($ancestorIds) {
                $query->active()->whereIn('shipping_location_id', $ancestorIds);
            })
            ->exists();
    }

    /**
     * Сохранено для обратной совместимости вызывающего кода.
     */
    public function hasRulesForLocation(ShippingLocation $location): bool
    {
        return $this->hasConfiguredMethodsForLocation($location);
    }

    /**
     * @param array<int, int> $distance
     * @return array<string, mixed>|null
     */
    private function buildOption(
        WarehouseDeliveryMethod $method,
        ShippingLocation $location,
        array $distance,
        float $orderAmount
    ): ?array {
        $zone = $this->resolveZone($method->zones, $distance);

        if (! $zone || ! $method->warehouse || ! $method->shippingMethod) {
            return null;
        }

        $shippingMethod = $method->shippingMethod;
        $carrier = $shippingMethod->carrier;
        $config = is_array($shippingMethod->configuration)
            ? $shippingMethod->configuration
            : [];
        $fallbackDays = $location->getEffectiveDeliveryDays();

        $basePrice = $zone->delivery_price !== null
            ? (float) $zone->delivery_price
            : (float) ($config['base_price'] ?? $location->getEffectiveDeliveryPrice() ?? 0);

        $freeThreshold = $zone->free_delivery_threshold;
        if ($freeThreshold === null && array_key_exists('free_delivery_threshold', $config)) {
            $freeThreshold = $config['free_delivery_threshold'];
        }
        if ($freeThreshold === null) {
            $freeThreshold = $location->getEffectiveFreeDeliveryThreshold();
        }

        $deliveryDaysMin = $zone->delivery_days_min
            ?? $config['delivery_days_min']
            ?? ($fallbackDays['min'] ?? null);
        $deliveryDaysMax = $zone->delivery_days_max
            ?? $config['delivery_days_max']
            ?? ($fallbackDays['max'] ?? null);

        $deliveryPrice = $basePrice;
        if ($freeThreshold !== null && $orderAmount >= (float) $freeThreshold) {
            $deliveryPrice = 0.0;
        }

        return [
            'warehouse_delivery_method_id' => (int) $method->id,
            'warehouse_id' => (int) $method->warehouse_id,
            'warehouse_name' => (string) $method->warehouse->name,
            'shipping_method_id' => (int) $method->shipping_method_id,
            'shipping_method_name' => (string) $shippingMethod->name,
            'carrier' => $carrier ? [
                'id' => (int) $carrier->id,
                'name' => (string) $carrier->name,
                'code' => $carrier->code ?? null,
            ] : null,
            'location_id' => (int) $zone->shipping_location_id,
            'inherited' => (int) $zone->shipping_location_id !== (int) $location->id,
            'delivery_base_price' => $basePrice,
            'delivery_price' => $deliveryPrice,
            'free_delivery_threshold' => $freeThreshold !== null
                ? (float) $freeThreshold
                : null,
            'delivery_days_min' => $deliveryDaysMin,
            'delivery_days_max' => $deliveryDaysMax,
            'method_priority' => (int) $method->priority,
            'zone_priority' => (int) $zone->priority,
        ];
    }

    /**
     * @param Collection<int, WarehouseDeliveryZone> $zones
     * @param array<int, int> $distance
     */
    private function resolveZone(Collection $zones, array $distance): ?WarehouseDeliveryZone
    {
        return $zones
            ->sortBy([
                fn (WarehouseDeliveryZone $zone) => $distance[$zone->shipping_location_id] ?? PHP_INT_MAX,
                fn (WarehouseDeliveryZone $zone) => -$zone->priority,
                fn (WarehouseDeliveryZone $zone) => $zone->id,
            ])
            ->first();
    }

    /**
     * @param Collection<int, array<string, mixed>> $options
     * @param array<int, array{product_id:int, quantity:int|float}> $items
     * @return Collection<int, array<string, mixed>>
     */
    private function filterByWarehouseStock(Collection $options, array $items): Collection
    {
        if (! $this->shouldFilterByWarehouseStock($items)) {
            return $options;
        }

        $normalizedItems = collect($items)
            ->filter(fn (array $item) => isset($item['product_id'], $item['quantity'])
                && (float) $item['quantity'] > 0)
            ->map(fn (array $item) => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (float) $item['quantity'],
            ])
            ->values();

        if ($normalizedItems->isEmpty()) {
            return $options;
        }

        $warehouseIds = $options->pluck('warehouse_id')->unique()->all();
        $productIds = $normalizedItems->pluck('product_id')->unique()->all();

        $stockRows = ProductWarehouseStock::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('product_id', $productIds)
            ->get(['warehouse_id', 'product_id', 'quantity'])
            ->groupBy('warehouse_id');

        return $options
            ->filter(function (array $option) use ($normalizedItems, $stockRows): bool {
                $stocks = $stockRows
                    ->get($option['warehouse_id'], collect())
                    ->keyBy('product_id');

                foreach ($normalizedItems as $item) {
                    $available = (float) ($stocks->get($item['product_id'])?->quantity ?? 0);

                    if ($available < $item['quantity']) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    private function shouldFilterByWarehouseStock(array $items): bool
    {
        if ($items === []) {
            return false;
        }

        return (bool) ProductStockSettings::getInstance()->warehouse_accounting_enabled;
    }
}
