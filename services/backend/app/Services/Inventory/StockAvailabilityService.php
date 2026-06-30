<?php

namespace App\Services\Inventory;

use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Support\Facades\Log;

class StockAvailabilityService
{
    /** @var array<int, ShippingLocation|null> */
    private static array $locationCache = [];

    public function __construct(
        protected WarehouseStockResolver $warehouseStockResolver,
    ) {
    }

    public function resolveAvailableStock(Product $product, ?int $shippingLocationId = null): ?int
    {
        $settings = ProductStockSettings::getInstance();
        if (! $settings->warehouse_accounting_enabled) {
            return $product->stock === null ? null : max(0, (int) $product->stock);
        }

        $location = null;
        if ($shippingLocationId !== null) {
            if (!array_key_exists($shippingLocationId, self::$locationCache)) {
                self::$locationCache[$shippingLocationId] = ShippingLocation::query()->find($shippingLocationId);
                Log::debug('Stock availability location cache miss', [
                    'shipping_location_id' => $shippingLocationId,
                    'found' => self::$locationCache[$shippingLocationId] !== null,
                ]);
            } else {
                Log::debug('Stock availability location cache hit', [
                    'shipping_location_id' => $shippingLocationId,
                ]);
            }
            $location = self::$locationCache[$shippingLocationId];
        }

        $resolved = max(0, (int) round($this->warehouseStockResolver->resolveForProduct($product, $location) ?? 0));
        Log::info('Resolved available stock for product', [
            'product_id' => $product->id,
            'shipping_location_id' => $shippingLocationId,
            'resolved_stock' => $resolved,
        ]);

        return $resolved;
    }
}
