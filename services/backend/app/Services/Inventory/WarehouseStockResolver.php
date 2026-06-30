<?php

namespace App\Services\Inventory;

use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Support\Facades\Log;

class WarehouseStockResolver
{
    /** @var array<string, float> */
    private static array $resolvedStockCache = [];

    public function resolveForProduct(Product $product, ?ShippingLocation $location): ?float
    {
        $cacheKey = $this->buildCacheKey($product->id, $location?->id);
        if (array_key_exists($cacheKey, self::$resolvedStockCache)) {
            Log::debug('Warehouse stock resolver cache hit', [
                'cache_key' => $cacheKey,
                'product_id' => $product->id,
                'location_id' => $location?->id,
            ]);

            return self::$resolvedStockCache[$cacheKey];
        }

        if ($product->isVariable() && ! $product->isVariant()) {
            $resolved = $this->resolveVariableParentStock($product, $location);
            self::$resolvedStockCache[$cacheKey] = $resolved;

            return $resolved;
        }

        $resolved = $this->resolveSingleProductStock($product, $location);
        self::$resolvedStockCache[$cacheKey] = $resolved;

        return $resolved;
    }

    /**
     * Остаток вариативного родителя = сумма остатков активных вариаций (по складам или products.stock).
     */
    private function resolveVariableParentStock(Product $product, ?ShippingLocation $location): float
    {
        $variants = $product->variants()->active()->get();

        if ($variants->isEmpty()) {
            return $this->resolveSingleProductStock($product, $location);
        }

        $total = 0.0;
        foreach ($variants as $variant) {
            $total += $this->resolveSingleProductStock($variant, $location);
        }

        Log::debug('Warehouse stock resolver variable parent aggregated', [
            'product_id' => $product->id,
            'location_id' => $location?->id,
            'variants_count' => $variants->count(),
            'resolved_stock' => $total,
        ]);

        return $total;
    }

    /**
     * Остаток одной SKU (родитель без вариаций, вариация или «сольной» вариативный товар).
     */
    private function resolveSingleProductStock(Product $product, ?ShippingLocation $location): float
    {
        $settings = ProductStockSettings::getInstance();

        if (! $settings->warehouse_accounting_enabled) {
            return (float) ($product->getAttributes()['stock'] ?? 0);
        }

        $query = $product->warehouseStocks()->with('warehouse');

        if ($location !== null) {
            $locationIds = array_unique(array_merge([$location->id], $location->getAncestorsIds()));
            $query->whereHas('warehouse.shippingLocations', function ($q) use ($locationIds) {
                $q->whereIn('shipping_locations.id', $locationIds);
            });
        }

        $stocks = $query->get();

        if ($stocks->isNotEmpty()) {
            return (float) $stocks->sum('quantity');
        }

        if ($settings->fallback_to_first_warehouse) {
            $fallback = $product->warehouseStocks()
                ->select('product_warehouse_stocks.quantity')
                ->join('warehouses', 'warehouses.id', '=', 'product_warehouse_stocks.warehouse_id')
                ->where('warehouses.is_active', true)
                ->orderBy('product_warehouse_stocks.warehouse_id')
                ->value('product_warehouse_stocks.quantity');

            if ($fallback === null) {
                $fallback = $product->warehouseStocks()
                    ->orderBy('warehouse_id')
                    ->value('quantity');
            }

            if ($fallback !== null) {
                Log::debug('Warehouse stock resolver fallback used', [
                    'product_id' => $product->id,
                    'location_id' => $location?->id,
                    'resolved_stock' => (float) $fallback,
                ]);

                return (float) $fallback;
            }
        }

        // Вариации после переключения на «вариативный» часто имеют остаток только в products.stock
        $attributeStock = (float) ($product->getAttributes()['stock'] ?? 0);

        Log::debug('Warehouse stock resolver using products.stock column', [
            'product_id' => $product->id,
            'location_id' => $location?->id,
            'resolved_stock' => $attributeStock,
        ]);

        return $attributeStock;
    }

    private function buildCacheKey(int $productId, ?int $locationId): string
    {
        return $productId . ':' . ($locationId ?? 'none');
    }

    public static function clearCache(): void
    {
        self::$resolvedStockCache = [];
    }
}
