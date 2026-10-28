<?php

namespace App\Services\Shipping;

use App\Models\Inventory\Warehouse;
use App\Services\Inventory\CartStockReservations;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Delivery from multiple physical sources, restricted to a fully expanded bundle.
 *
 * A SKU quantity can be split across warehouses; components may use different
 * sources. Each source must independently cover the destination and have its
 * own usable delivery tariff. We never pretend goods can be transferred for
 * free or silently use the lead warehouse's stock for the entire bundle.
 */
class MultiWarehouseBundleDelivery
{
    /**
     * @param Collection<int,Warehouse> $warehouses eager-loaded as WarehouseDeliveryQuote does
     * @param array<int,int> $quantities physical SKU => units
     * @return array<int,array<string,mixed>>
     */
    public function options(Collection $warehouses, array $quantities, array $destination, ?array $point, array $unitPrices, array $selection): array
    {
        if (! config('bundles.multi_warehouse_enabled', false)) {
            return [];
        }
        if (count($quantities) < 1 || ! empty($selection['additional_services'])
            || ! empty($selection['requires_assembly'])
            || ! empty($selection['delivery_handling_type_id'])) {
            // Cross-warehouse assembly/lifting/transfers require a separate tariff.
            return [];
        }

        $sources = [];
        foreach ($warehouses as $warehouse) {
            $distance = $point ? app(WarehouseDistance::class)->kilometres(
                $warehouse, $point['latitude'], $point['longitude']
            ) : null;

            $profile = $warehouse->deliveryProfiles->first(function ($profile) use ($warehouse, $destination, $distance): bool {
                if (! app(WarehouseCoverage::class)->profileMatches($profile, $warehouse, $destination)
                    || ! empty($profile->constraints) || ($profile->price_per_km > 0 && $distance === null)) {
                    return false;
                }
                try {
                    // Multi-source delivery cannot assume any automatic assembly fees.
                    $services = app(WarehouseServices::class)->calculate($profile, []);
                    return ! $services['requires_assembly'] && $services['handling_cost'] == 0
                        && $services['assembly_cost'] == 0 && $services['additional_services_cost'] == 0;
                } catch (ValidationException $e) {
                    return false;
                }
            });
            if (! $profile) {
                continue;
            }
            $eligible = [];
            $stocks = $warehouse->productStocks->keyBy('product_id');
            $availabilities = $warehouse->productAvailabilities->keyBy('product_id');
            foreach ($quantities as $id => $count) {
                if ($warehouse->stock_mode === 'availability') {
                    if (! ($availabilities[$id]->available_to_order ?? false)) {
                        continue;
                    }
                } elseif ($warehouse->stock_mode === 'quantity') {
                    // The caller has already reduced quantity to free stock.
                    if (($stocks[$id]->quantity ?? 0) < 1) {
                        continue;
                    }
                } else {
                    continue;
                }
                $eligible[(int) $id] = $warehouse->stock_mode === 'quantity'
                    ? min((int) $count, (int) $stocks[$id]->quantity) : (int) $count;
            }
            if ($eligible === []) {
                continue;
            }
            $sources[$warehouse->id] = [
                'warehouse' => $warehouse, 'profile' => $profile,
                'eligible' => $eligible, 'distance' => $distance,
                'transport' => round((float) $profile->base_price + (float) $profile->price_per_km * ($distance ?? 0), 2),
            ];
        }

        // No candidate can fulfil all components, including source-specific delivery.
        foreach (array_keys($quantities) as $skuId) {
            if (! collect($sources)->contains(fn ($source) => isset($source['eligible'][$skuId]))) {
                return [];
            }
        }

        // Stable greedy set-cover: minimize shipments first, then published transport
        // rates. Each source contributes only its physically available units.
        $pending = $quantities;
        $allocations = [];
        while ($pending !== []) {
            $best = null;
            foreach ($sources as $warehouseId => $source) {
                $coverage = [];
                foreach ($pending as $skuId => $remaining) {
                    $take = min($remaining, $source['eligible'][$skuId] ?? 0);
                    if ($take > 0) {
                        $coverage[$skuId] = $take;
                    }
                }
                if (! $coverage) {
                    continue;
                }
                $score = [-count($coverage), $source['transport'], (int) $warehouseId];
                if ($best === null || $score < $best['score']) {
                    $best = ['score' => $score, 'id' => $warehouseId, 'source' => $source, 'items' => $coverage];
                }
            }
            if ($best === null) {
                return [];
            }
            $warehouse = $best['source']['warehouse'];
            $profile = $best['source']['profile'];
            $items = collect($best['items'])->map(fn (int $quantity, int $id) => [
                'product_id' => (int) $id, 'quantity' => $quantity,
            ])->values()->all();
            $sourceSubtotal = 0.0;
            foreach ($best['items'] as $skuId => $qty) {
                $sourceSubtotal += (float) ($unitPrices[$skuId] ?? 0) * $qty;
            }
            $transportCost = $best['source']['transport'];
            if ($profile->free_delivery_threshold !== null
                && $sourceSubtotal >= (float) $profile->free_delivery_threshold) {
                $transportCost = 0.0;
            }
            $allocations[] = [
                'warehouse_id' => (int) $warehouse->id,
                'warehouse_name' => $warehouse->name,
                'warehouse_external_id' => $warehouse->external_id,
                'stock_mode' => $warehouse->stock_mode,
                'warehouse_fingerprint' => hash('sha256', json_encode([
                    $warehouse->getAttributes(), $warehouse->physicalSite?->getAttributes(),
                ])),
                'profile_id' => $profile->id,
                'profile_fingerprint' => hash('sha256', json_encode($profile->getAttributes())),
                'items' => $items,
                'transport_cost' => $transportCost,
                'processing_days_min' => (int) $warehouse->processing_days_min,
                'processing_days_max' => (int) $warehouse->processing_days_max,
                'delivery_days_min' => (int) $profile->delivery_days_min,
                'delivery_days_max' => (int) $profile->delivery_days_max,
            ];
            if ($warehouse->stock_mode === 'availability') {
                foreach (array_keys($best['items']) as $skuId) {
                    $availability = $warehouse->productAvailabilities->firstWhere('product_id', $skuId);
                    $allocations[array_key_last($allocations)]['processing_days_min'] = max(
                        $allocations[array_key_last($allocations)]['processing_days_min'], (int) ($availability->processing_days_min ?? 0)
                    );
                    $allocations[array_key_last($allocations)]['processing_days_max'] = max(
                        $allocations[array_key_last($allocations)]['processing_days_max'], (int) ($availability->processing_days_max ?? 0)
                    );
                }
            }
            foreach ($best['items'] as $skuId => $taken) {
                $pending[$skuId] -= $taken;
                if ($pending[$skuId] === 0) {
                    unset($pending[$skuId]);
                }
            }
            unset($sources[$best['id']]);
        }
        if (count($allocations) < 2) {
            return [];
        }

        // Every contributing warehouse has a delivery profile and charges once.
        $transport = round(array_sum(array_column($allocations, 'transport_cost')), 2);
        $maxMin = max(array_map(fn ($a): int => $a['processing_days_min'] + $a['delivery_days_min'], $allocations));
        $maxMax = max(array_map(fn ($a): int => $a['processing_days_max'] + $a['delivery_days_max'], $allocations));
        $lead = $allocations[0];
        $warehouse = $warehouses->firstWhere('id', $lead['warehouse_id']);
        $profile = $warehouse->deliveryProfiles->firstWhere('id', $lead['profile_id']);
        return [[
            'mode' => 'delivery', 'multi_warehouse' => true,
            'warehouse_id' => $lead['warehouse_id'], 'warehouse_name' => $lead['warehouse_name'],
            'warehouse_external_id' => $lead['warehouse_external_id'],
            'stock_mode' => 'multi',
            'profile_id' => $lead['profile_id'],
            'profile_name' => $profile->name,
            'profile_fingerprint' => $lead['profile_fingerprint'],
            'warehouse_fingerprint' => $lead['warehouse_fingerprint'],
            'warehouse_allocations' => $allocations,
            'delivery_cost' => $transport, 'transport_cost' => $transport,
            'handling_cost' => 0.0, 'assembly_cost' => 0.0,
            'additional_services_cost' => 0.0, 'additional_services' => [],
            'requires_assembly' => false, 'assembly_days' => null,
            'service_options' => [
                'assembly' => ['available' => false, 'required' => false, 'price' => null, 'days' => null],
                'handling' => [], 'additional' => [],
            ],
            'total_fulfillment_cost' => $transport,
            'base_price' => $transport, 'free_delivery_threshold' => null,
            'transit_days_min' => $maxMin, 'transit_days_max' => $maxMax,
            'processing_days_min' => 0, 'processing_days_max' => 0,
            'delivery_days_min' => $maxMin, 'delivery_days_max' => $maxMax,
            'delivery' => app(FulfillmentDates::class)->range($maxMin, $maxMax),
            'distance_km' => null, 'distance_type' => 'per_warehouse',
            'items' => collect($quantities)->map(fn (int $qty, int $sku) => [
                'product_id' => (int) $sku, 'quantity' => $qty,
            ])->values()->all(),
        ]];
    }
}
