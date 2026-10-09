<?php

namespace App\Services\Shipping;

use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\WarehouseDeliveryProfile;
use Illuminate\Support\Collection;

class FulfillmentSourceResolver
{
    /**
     * Find active sources that can supply a concrete product/SKU and deliver it
     * to the customer's locality. Parent locations are inherited.
     */
    public function resolve(Product $product, ShippingLocation $destination, ?array $coordinates = null): Collection
    {
        if ($coordinates !== null) {
            if (! isset($coordinates['latitude'], $coordinates['longitude'])
                || ! is_numeric($coordinates['latitude']) || ! is_numeric($coordinates['longitude'])) {
                throw new \InvalidArgumentException('Both destination coordinates are required');
            }
            // Validate even if no stock/profile matches.
            app(WarehouseDistance::class)->kilometres(new Warehouse, (float) $coordinates['latitude'], (float) $coordinates['longitude']);
        }
        $locationIds = $this->locationPathIds($destination);

        return Warehouse::query()
            ->where('is_active', true)
            ->with([
                'physicalSite',
                'deliveryProfiles' => fn ($query) => $query->active()->with('locations'),
                'productStocks' => fn ($query) => $query->where('product_id', $product->id),
                'productAvailabilities' => fn ($query) => $query->where('product_id', $product->id),
            ])
            ->get()
            ->map(function (Warehouse $warehouse) use ($locationIds, $coordinates) {
                $profile = $warehouse->deliveryProfiles
                    ->filter(fn (WarehouseDeliveryProfile $profile) => in_array($profile->coverage_type, ['locations', 'hybrid'], true))
                    ->filter(fn (WarehouseDeliveryProfile $profile) => $profile->locations->pluck('id')->intersect($locationIds)->isNotEmpty())
                    ->sortBy('priority')
                    ->first();

                if (! $profile) {
                    return null;
                }

                $stock = $warehouse->productStocks->first();
                $availability = $warehouse->productAvailabilities->first();
                $canSupply = $warehouse->stock_mode === 'availability'
                    ? (bool) $availability?->available_to_order
                    : (float) ($stock?->quantity ?? 0) > 0;

                if (! $canSupply) {
                    return null;
                }

                return [
                    'warehouse_id' => $warehouse->id,
                    'source_name' => $warehouse->name,
                    'distance_km' => $coordinates !== null
                        ? app(WarehouseDistance::class)->kilometres($warehouse, $coordinates['latitude'], $coordinates['longitude'])
                        : null,
                    'distance_type' => 'straight_line',
                    'distance_available' => $coordinates !== null && $warehouse->dispatchCoordinates() !== null,
                    'source_type' => $warehouse->source_type,
                    'stock_mode' => $warehouse->stock_mode,
                    'quantity' => $stock?->quantity,
                    'available_to_order' => $availability?->available_to_order,
                    'profile_id' => $profile->id,
                    'profile_name' => $profile->name,
                    'base_price' => (float) $profile->base_price,
                    'delivery_days_min' => (int) $profile->delivery_days_min,
                    'delivery_days_max' => (int) $profile->delivery_days_max,
                    'processing_days_min' => (int) ($availability?->processing_days_min ?? $warehouse->processing_days_min),
                    'processing_days_max' => (int) ($availability?->processing_days_max ?? $warehouse->processing_days_max),
                ];
            })
            ->filter()
            ->sortBy([
                ...($coordinates !== null ? [['distance_available', 'desc'], ['distance_km', 'asc']] : []),
                ['delivery_days_min', 'asc'],
                ['base_price', 'asc'],
            ])
            ->values();
    }

    private function locationPathIds(ShippingLocation $location): array
    {
        $ids = [(int) $location->id];
        $cursor = $location;

        while ($cursor->parent_id) {
            $cursor = ShippingLocation::find($cursor->parent_id);
            if (! $cursor) {
                break;
            }
            $ids[] = (int) $cursor->id;
        }

        return $ids;
    }
}
