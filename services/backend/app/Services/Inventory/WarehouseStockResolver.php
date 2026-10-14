<?php

namespace App\Services\Inventory;

use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Models\Shipping\ShippingLocation;
use App\Services\Shipping\WarehouseCoverage;

class WarehouseStockResolver
{
    public function resolveForProduct(Product $product, ?ShippingLocation $location, ?array $destination = null): ?float
    {
        $destination ??= request()->attributes->get('warehouse_destination')
            ?? app(WarehouseCoverage::class)->destination($location);
        $settings = ProductStockSettings::getInstance();
        if (! $settings->warehouse_accounting_enabled) {
            // A global legacy number is not proof of stock in the selected city.
            return $destination['specified'] ? null : (float) $product->stock;
        }
        if ($product->isVariable() && ! $product->isVariant()) {
            $variants = $product->variants()->active()->get();
            if ($variants->isNotEmpty()) {
                return (float) $variants->sum(fn ($sku) => $this->resolveForProduct($sku, $location, $destination) ?? 0);
            }
        }

        return (float) $this->eligibleStocks($product, $location, $destination)->sum(fn ($stock) => max(0, (float) $stock->quantity));
    }

    public function eligibleStocks(Product $product, ?ShippingLocation $location, ?array $destination = null): \Illuminate\Support\Collection
    {
        $destination ??= request()->attributes->get('warehouse_destination') ?? app(WarehouseCoverage::class)->destination($location);
        return $product->warehouseStocks()
            ->whereHas('warehouse', fn ($q) => $q->where('is_active', true)->where('stock_mode', 'quantity'))
            ->with(['warehouse.physicalSite', 'warehouse.shippingLocations', 'warehouse.deliveryProfiles.locations'])
            ->get()->filter(function ($stock) use ($destination): bool {
                if (! $destination['specified']) {
                    return true;
                }
                $warehouse = $stock->warehouse;
                if ($warehouse->deliveryProfiles->isNotEmpty()) {
                    return app(WarehouseCoverage::class)->firstProfile($warehouse, $destination) !== null;
                }

                // Explicit legacy location bindings remain valid only without any new profiles.
                return $warehouse->shippingLocations->pluck('id')->intersect($destination['location_ids'])->isNotEmpty();
            })->values();
    }

    public static function clearCache(): void
    {
        // No process-global SKU cache: request geography and live inventory must not leak.
    }

    public function availableToOrder(Product $product, ?ShippingLocation $location): bool
    {
        $destination = request()->attributes->get('warehouse_destination') ?? app(WarehouseCoverage::class)->destination($location);
        if (! ProductStockSettings::getInstance()->warehouse_accounting_enabled) {
            return ! $destination['specified'] && (bool) $product->backorder;
        }
        if ($product->isVariable() && ! $product->isVariant()) {
            return $product->variants()->active()->get()->contains(fn ($sku) => $this->availableToOrder($sku, $location));
        }
        return \App\Models\Inventory\SourceProductAvailability::where('product_id', $product->id)->where('available_to_order', true)
            ->whereHas('warehouse', fn ($q) => $q->where('is_active', true)->where('stock_mode', 'availability'))
            ->with(['warehouse.physicalSite', 'warehouse.deliveryProfiles.locations', 'warehouse.shippingLocations'])->get()
            ->contains(function ($row) use ($destination): bool {
                return ! $destination['specified'] || app(WarehouseCoverage::class)->firstProfile($row->warehouse, $destination) !== null;
            });
    }
}
