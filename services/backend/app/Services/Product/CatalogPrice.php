<?php

namespace App\Services\Product;

use App\Models\Shipping\ShippingLocation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Single pricing SQL entry for catalogue, facets and checkout. */
class CatalogPrice
{
    public function rows(?ShippingLocation $location, bool $applyPricingRules = true): Builder
    {
        $base = $this->baseRows($location);
        return $applyPricingRules ? app(\App\Services\Pricing\PricingEngine::class)->rows($base, $location, app(\App\Services\Pricing\PricingEngine::class)->customer()) : $base;
    }

    /**
     * The base price is the SKU price. Historical product_region_rules must
     * not change it: region-aware pricing belongs exclusively to PricingEngine.
     *
     * Keeping the location parameter preserves the public SQL contract.
     */
    private function baseRows(?ShippingLocation $location): Builder
    {
        return DB::table('products')
            ->selectRaw('products.id AS sku_id')
            ->selectRaw('products.price AS effective_price');
    }
}
