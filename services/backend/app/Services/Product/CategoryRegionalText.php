<?php

namespace App\Services\Product;

use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use App\Services\Address\AddressDirectoryClient;
use Illuminate\Http\Request;
use Vanilo\Category\Models\Taxon;

class CategoryRegionalText
{
    public function render(Taxon $category, Request $request): array
    {
        $template = $category->regional_text_template;
        if (! $template) {
            return ['text' => null, 'city' => null, 'delivery_cities_count' => null, 'count_source' => 'not_requested'];
        }
        $city = null;
        if ($request->filled('directory_external_id')) {
            try {
                $snapshot = app(AddressDirectoryClient::class)->hierarchy($request->input('directory_external_id'));
            } catch (\RuntimeException $error) {
                report($error);

                return ['text' => null, 'city' => null, 'delivery_cities_count' => null, 'count_source' => 'address_directory_unavailable'];
            }
            $city = collect($snapshot['path'] ?? [])->last(fn ($node) => ($node['kind'] ?? null) === 'locality');
        } elseif ($id = $request->input('shipping_location_id', $request->input('region_id'))) {
            $location = ShippingLocation::where('is_active', true)->where('type', 'locality')->find($id);
            $city = $location ? ['name' => $location->name, 'externalId' => $location->code, 'kladrCode' => $location->code] : null;
        }
        $roots = Product::active()->whereNull('parent_product_id')->inCategory($category->id);
        $skuIds = app(CatalogFilterService::class)->skus($roots, [])->select('products.id');
        $warehouses = Warehouse::where('is_active', true)->where(fn ($q) => $q
            ->where(fn ($quantity) => $quantity->where('stock_mode', 'quantity')->whereHas('productStocks', fn ($s) => $s->where('quantity', '>', 0)->whereIn('product_id', clone $skuIds)))
            ->orWhere(fn ($availability) => $availability->where('stock_mode', 'availability')->whereHas('productAvailabilities', fn ($s) => $s->where('available_to_order', true)->whereIn('product_id', clone $skuIds))))
            ->with(['deliveryProfiles' => fn ($p) => $p->active()->with('locations')])->get();
        $cities = [];
        $complete = true;
        foreach ($warehouses as $warehouse) {
            foreach ($warehouse->deliveryProfiles as $profile) {
                if (! empty($profile->constraints)) {
                    continue;
                }
                if ($profile->coverage_type !== 'locations') {
                    $complete = false;

                    continue;
                }
                if ($profile->directory_territories) {
                    foreach ($profile->directory_territories as $territory) {
                        if (($territory['kind'] ?? null) !== 'locality') {
                            $complete = false;
                        } else {
                            $cities[isset($territory['kladrCode']) ? rtrim($territory['kladrCode'], '0') : $territory['externalId']] = true;
                        }
                    }
                } else {
                    foreach ($profile->locations as $location) {
                        if ($location->type !== 'locality' || ! $location->code) {
                            $complete = false;
                        } else {
                            $cities[rtrim($location->code, '0')] = true;
                        }
                    }
                }
            }
        }
        $count = $complete ? count($cities) : null;
        $needsCity = str_contains($template, '{city}');
        $needsCount = str_contains($template, '{delivery_cities_count}');
        $text = ($needsCity && ! $city) || ($needsCount && $count === null) ? null
            : strtr(strip_tags($template), ['{category_name}' => strip_tags($category->name), '{city}' => strip_tags($city['name'] ?? ''), '{delivery_cities_count}' => (string) $count]);

        return ['text' => $text, 'city' => $city['name'] ?? null, 'delivery_cities_count' => $count,
            'count_source' => $complete ? 'active_stock_sources_explicit_localities' : 'unknown_for_region_or_radius_coverage'];
    }
}
