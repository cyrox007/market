<?php

namespace App\Services\Pricing;

use App\Models\Pricing\PricingProductGroup;
use App\Models\Pricing\PricingRule;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Product\Room;
use App\Models\Shipping\ShippingLocation;
use App\Services\Product\CatalogFilterService;
use Illuminate\Database\Eloquent\Builder;

class PricingTargets
{
    public function skus(PricingRule $rule, ?ShippingLocation $location): Builder
    {
        $roots = Product::active()->whereNull('parent_product_id');
        $filters = (new CatalogFilterService)->forLocation($location)->withoutPricingRules();
        $sku = $filters->skus($roots, [])->select('products.id');
        if ($rule->target_type === 'products' || $rule->target_type === 'groups') {
            $ids = $rule->target_type === 'products' ? $rule->product_ids
                : PricingProductGroup::whereIn('id', $rule->product_group_ids ?? [])->get()->flatMap(fn ($group) => $group->product_ids)->all();
            $sku->where(fn ($q) => $q->whereIn('products.id', $ids ?? [])->orWhereIn('products.parent_product_id', $ids ?? []));
        } elseif ($rule->target_type === 'categories') {
            $ids = collect($rule->category_ids)->flatMap(fn ($id) => Category::getAllDescendantIdsFor($id))->unique()->all();
            $rootIds = Product::query()->whereHas('taxons', fn ($q) => $q->whereIn('taxons.id', $ids))->select('products.id');
            $sku->where(fn ($q) => $q->whereIn('products.id', clone $rootIds)->orWhereIn('products.parent_product_id', clone $rootIds));
        } elseif ($rule->target_type === 'rooms') {
            $roomQueries = [];
            foreach (Room::whereIn('id', $rule->room_ids ?? [])->with('productCategories')->get() as $room) {
                $ids = $room->productCategories->flatMap(fn ($cat) => $cat->getAllDescendantIds())->unique()->all();
                $roomRoots = Product::active()->whereNull('parent_product_id')->whereHas('taxons', fn ($q) => $q->whereIn('taxons.id', $ids));
                $roomQueries[] = $filters->skus($roomRoots, $filters->filters([], $room->effectiveFilters()))->select('products.id');
            }
            $sku->where(function ($q) use ($roomQueries) {
                $q->whereRaw('1 = 0');
                foreach ($roomQueries as $roomQuery) {
                    $q->orWhereIn('products.id', $roomQuery);
                }
            });
        } elseif ($rule->target_type !== 'all') {
            $sku->whereRaw('1 = 0');
        }

        return $sku->whereNotIn('products.id', $rule->excluded_product_ids ?? [])
            ->where(fn ($q) => $q->whereNull('products.parent_product_id')->orWhereNotIn('products.parent_product_id', $rule->excluded_product_ids ?? []));
    }
}
