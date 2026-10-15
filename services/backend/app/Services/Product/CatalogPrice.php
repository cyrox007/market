<?php

namespace App\Services\Product;

use App\Models\Shipping\ShippingLocation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** SQL counterpart of region pricing, shared by list/count predicates, facets and ordering. */
class CatalogPrice
{
    public function rows(?ShippingLocation $location, bool $applyPricingRules = true): Builder
    {
        $base = $this->baseRows($location);
        return $applyPricingRules ? app(\App\Services\Pricing\PricingEngine::class)->rows($base, $location, app(\App\Services\Pricing\PricingEngine::class)->customer()) : $base;
    }

    private function baseRows(?ShippingLocation $location): Builder
    {
        $rows = DB::table('products')->selectRaw('products.id AS sku_id');
        if (! $location) {
            return $rows->selectRaw('products.price AS effective_price');
        }
        $ids = $location->getAncestorsIds(); // current location first
        $rule = DB::table('product_region_rules as pr')->where('pr.is_active', true)
            ->whereIn('pr.shipping_location_id', $ids)
            ->whereRaw('pr.product_id = COALESCE(products.parent_product_id, products.id)')
            ->where(fn ($q) => $q->whereColumn('pr.variant_id', 'products.id')->orWhereNull('pr.variant_id'))
            ->selectRaw("COALESCE(pr.price_override, CASE pr.price_modifier_type WHEN 'fixed' THEN products.price + pr.price_modifier_value WHEN 'percent' THEN products.price * (1 + pr.price_modifier_value / 100.0) WHEN 'multiply' THEN products.price * pr.price_modifier_value ELSE products.price END, products.price)")
            ->orderByRaw('CASE pr.shipping_location_id '.implode(' ', array_map(fn ($id, $rank) => 'WHEN '.(int) $id.' THEN '.$rank, $ids, array_keys($ids))).' END')
            ->orderByRaw('CASE WHEN pr.variant_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('pr.priority')->orderByDesc('pr.id')->limit(1);

        return $rows->selectRaw('ROUND(COALESCE(('.$rule->toSql().'), products.price), 2) AS effective_price', $rule->getBindings());
    }
}
