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
            ->with(['warehouse.physicalSite', 'warehouse.deliveryProfiles'])
            ->get()->filter(function ($stock) use ($destination): bool {
                if (! $destination['specified']) {
                    return true;
                }
                $warehouse = $stock->warehouse;
                return app(WarehouseCoverage::class)->firstProfile($warehouse, $destination) !== null;
            })->map(function ($stock) {
                $stock->quantity = app(CartStockReservations::class)->available($stock->warehouse_id, $stock->product_id, (float) $stock->quantity);
                return $stock;
            })->values();
    }

    public static function clearCache(): void
    {
        // No process-global SKU cache: request geography and live inventory must not leak.
    }

    /** Можно заказать: под заказ в регионе или с доставкой со склада другого региона. */
    public function canOrder(Product $product, ?ShippingLocation $location): bool
    {
        return $this->availableToOrder($product, $location) || $this->onlyInOtherRegion($product, $location);
    }

    /** Свободный остаток есть только на складах вне зоны доставки покупателя. */
    private function onlyInOtherRegion(Product $product, ?ShippingLocation $location): bool
    {
        $destination = request()->attributes->get('warehouse_destination') ?? app(WarehouseCoverage::class)->destination($location);
        if (! $destination['specified']) {
            return false;
        }
        if ($product->isVariable() && ! $product->isVariant()) {
            return $product->variants()->active()->get()->contains(fn ($sku) => $this->onlyInOtherRegion($sku, $location));
        }
        $stocks = $product->warehouseStocks()
            ->whereHas('warehouse', fn ($q) => $q->where('is_active', true)->where('stock_mode', 'quantity'))
            ->with(['warehouse.physicalSite', 'warehouse.deliveryProfiles'])->get()
            ->filter(fn ($stock) => app(CartStockReservations::class)->available($stock->warehouse_id, $stock->product_id, (float) $stock->quantity) >= 1);

        return $stocks->isNotEmpty()
            && ! $stocks->contains(fn ($stock) => app(WarehouseCoverage::class)->firstProfile($stock->warehouse, $destination) !== null);
    }

    public function availableToOrder(Product $product, ?ShippingLocation $location): bool
    {
        $destination = request()->attributes->get('warehouse_destination') ?? app(WarehouseCoverage::class)->destination($location);
        if ($product->isVariable() && ! $product->isVariant()) {
            return $product->variants()->active()->get()->contains(fn ($sku) => $this->availableToOrder($sku, $location));
        }
        if ($product->backorder && $this->eligibleStocks($product, $location, $destination)->isNotEmpty()) {
            return true;
        }
        return \App\Models\Inventory\SourceProductAvailability::where('product_id', $product->id)
            ->when(! $product->backorder, fn ($q) => $q->where('available_to_order', true))
            ->whereHas('warehouse', fn ($q) => $q->where('is_active', true)->where('stock_mode', 'availability'))
            ->with(['warehouse.physicalSite', 'warehouse.deliveryProfiles'])->get()
            ->contains(function ($row) use ($destination): bool {
                return ! $destination['specified'] || app(WarehouseCoverage::class)->firstProfile($row->warehouse, $destination) !== null;
            });
    }
}
