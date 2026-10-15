<?php

namespace App\Services\Product;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use App\Models\Product\RoomFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Catalog predicates operate on one sellable SKU, never independent sibling variants. */
class CatalogFilterService
{
    public const DIMENSIONS = ['width' => 'sirina-mm', 'depth' => 'glubina-mm', 'height' => 'vysota-mm'];

    private array $attributesBySlug = [];

    private array $matches = [];
    private bool $applyPricingRules = true;

    public function withoutPricingRules(): static
    {
        $this->applyPricingRules = false;
        return $this;
    }

    private ?\App\Models\Shipping\ShippingLocation $location = null;

    public function forLocation(?\App\Models\Shipping\ShippingLocation $location): self
    {
        $this->location = $location;
        return $this;
    }

    private function attribute(string $slug): ?Attribute
    {
        if (! array_key_exists($slug, $this->attributesBySlug)) {
            $this->attributesBySlug[$slug] = Attribute::where('slug', $slug)->first();
        }

        return $this->attributesBySlug[$slug];
    }

    public function filters(array $input, array $mandatory = [], ?string $except = null): array
    {
        $attrs = $input['attributes'] ?? [];
        foreach (['colors' => Attribute::SLUG_COLOR, 'sizes' => Attribute::SLUG_SIZE] as $key => $slug) {
            $values = $this->tokens($input[$key] ?? []);
            $attrs[$slug] = array_values(array_unique(array_merge($this->tokens($attrs[$slug] ?? []), $values)));
        }
        if ($except !== null) {
            unset($attrs[$except], $input[$except.'_min'], $input[$except.'_max']);
            if (isset(self::DIMENSIONS[$except])) {
                unset($attrs[self::DIMENSIONS[$except]]);
            }
            if ($except === 'on_sale') {
                $input['on_sale'] = false;
            }
        }
        // Normalize both aliases before intersection: a room's attr_color cannot be
        // overridden by the user's colors alias (or vice versa).
        if (array_key_exists('colors', $mandatory)) {
            $roomColors = $this->tokens($mandatory['colors']);
            $mandatory['attributes'][Attribute::SLUG_COLOR] = isset($mandatory['attributes'][Attribute::SLUG_COLOR])
                ? array_values(array_intersect($roomColors, $this->tokens($mandatory['attributes'][Attribute::SLUG_COLOR])))
                : $roomColors;
            unset($mandatory['colors']);
        }
        $narrowed = RoomFilters::narrow($mandatory, [
            'price_min' => $input['price_min'] ?? null,
            'price_max' => $input['price_max'] ?? null,
            'attributes' => array_filter($attrs),
        ]);
        $attrs = $narrowed['attributes'] ?? [];

        return array_replace($input, $narrowed, ['attributes' => $attrs]);
    }

    public function apply(Builder $roots, array $filters): Builder
    {
        if (! $this->hasPredicates($filters)) {
            return $roots;
        }

        return $roots->whereIn('products.id', $this->skus($roots, $filters)->selectRaw('COALESCE(products.parent_product_id, products.id)'));
    }

    private function hasPredicates(array $filters): bool
    {
        if (! empty($filters['attributes']) || ! empty($filters['on_sale'])) {
            return true;
        }
        foreach (array_merge(['price'], array_keys(self::DIMENSIONS)) as $key) {
            foreach (['min', 'max'] as $bound) {
                if (isset($filters[$key.'_'.$bound]) && $filters[$key.'_'.$bound] !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    public function skus(Builder $roots, array $filters): Builder
    {
        $ids = (clone $roots)->reorder()->select('products.id')->setEagerLoads([]);
        $sku = Product::query()->active()->where(function ($q) use ($ids) {
            $q->where(function ($simple) use ($ids) {
                $simple->whereIn('products.id', clone $ids)->whereNull('parent_product_id')
                    ->where(fn ($v) => $v->whereNull('is_variable')->orWhere('is_variable', false)->orWhereDoesntHave('variants'));
            })->orWhere(function ($variant) use ($ids) {
                $variant->whereIn('products.parent_product_id', clone $ids);
            });
        });
        $sku->joinSub(app(CatalogPrice::class)->rows($this->location, $this->applyPricingRules), 'catalog_prices', 'catalog_prices.sku_id', '=', 'products.id');
        foreach (['min' => '>=', 'max' => '<='] as $bound => $operator) {
            if (isset($filters['price_'.$bound]) && $filters['price_'.$bound] !== '') {
                $sku->whereRaw('CAST(catalog_prices.effective_price AS DECIMAL(20,4)) '.$operator.' CAST(? AS DECIMAL(20,4))', [(float) $filters['price_'.$bound]]);
            }
        }
        if (! empty($filters['on_sale'])) {
            $sku->whereColumn($this->applyPricingRules ? 'catalog_prices.reference_price' : 'products.original_price', '>', 'catalog_prices.effective_price');
        }
        foreach ($filters['attributes'] ?? [] as $slug => $values) {
            $values = $this->tokens($values);
            if ($values === []) {
                $sku->whereRaw('1 = 0'); // Empty mandatory-room intersection must fail closed.

                continue;
            }
            $attribute = $this->attribute($slug);
            if (! $attribute) {
                $sku->whereRaw('1 = 0');

                continue;
            }
            [$resolved, $custom] = $this->resolveMatches($attribute, $values);
            $sku->whereExists(function ($sub) use ($attribute, $resolved, $custom) {
                $sub->selectRaw('1')->fromSub($this->effectiveRows($attribute), 'av')
                    ->whereColumn('av.sku_id', 'products.id')
                    ->where(function ($v) use ($resolved, $custom) {
                        $v->whereIn('av.attribute_value_id', $resolved)->orWhereIn('av.custom_value', $custom);
                    });
            });
        }
        foreach (self::DIMENSIONS as $key => $slug) {
            $min = $filters[$key.'_min'] ?? null;
            $max = $filters[$key.'_max'] ?? null;
            if ($min === null && $max === null) {
                continue;
            }
            $attribute = $this->attribute($slug);
            if (! $attribute) {
                $sku->whereRaw('1 = 0');

                continue;
            }
            $sku->whereExists(function ($sub) use ($attribute, $min, $max) {
                $sub->selectRaw('1')->fromSub($this->numericRows($attribute), 'dimension')
                    ->whereColumn('dimension.sku_id', 'products.id');
                if ($min !== null) {
                    $sub->where('dimension.mm', '>=', $min);
                }
                if ($max !== null) {
                    $sub->where('dimension.mm', '<=', $max);
                }
            });
        }

        return $sku;
    }

    private function resolveMatches(Attribute $attribute, array $values): array
    {
        sort($values);
        $key = $attribute->id.':'.json_encode($values);
        if (! isset($this->matches[$key])) {
            $tokens = collect($values)->flatMap(fn ($v) => [mb_strtolower($v), Str::slug($v)])->unique()->all();
            $resolved = $attribute->values()->get()->filter(fn ($v) => in_array(mb_strtolower($v->slug ?? ''), $tokens, true)
                || in_array(mb_strtolower($v->value), $tokens, true) || in_array(Str::slug($v->value), $tokens, true))->pluck('id')->all();
            // Resolve exact custom values once; do not use substring matches (red must not match redwood).
            $rows = $this->rows($attribute)->whereNotNull('custom_value')->distinct()->pluck('custom_value');
            $custom = $rows->filter(fn ($v) => in_array(mb_strtolower(trim($v)), $tokens, true)
                || in_array(Str::slug($v), $tokens, true))->all();
            $this->matches[$key] = [$resolved, $custom];
        }

        return $this->matches[$key];
    }

    private function tokens(mixed $values): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v),
            is_array($values) ? $values : explode(',', (string) $values)), fn ($v) => $v !== '')));
    }

    /** Both storage pivots are valid; a SKU's own attribute overrides an inherited root attribute. */
    private function rows(Attribute $attribute): \Illuminate\Database\Query\Builder
    {
        $regular = DB::table('product_product_attributes')->where('attribute_id', $attribute->id)
            ->select('product_id', 'attribute_value_id', 'custom_value');
        $variant = DB::table('product_variant_attributes')->where('attribute_id', $attribute->id)
            ->select('product_id', 'attribute_value_id', 'custom_value');

        return DB::query()->fromSub($regular->unionAll($variant), 'stored');
    }

    private function effectiveRows(Attribute $attribute): \Illuminate\Database\Query\Builder
    {
        $direct = $this->rows($attribute)->selectRaw('product_id as sku_id, attribute_value_id, custom_value');
        // Variation attributes are SKU-specific; parent's option list must not grant every SKU all colors.
        if ($attribute->is_use_in_variations || in_array($attribute->slug, [Attribute::SLUG_COLOR, Attribute::SLUG_SIZE], true)) {
            return $direct;
        }
        $inherited = DB::table('products as inheriting_sku')
            ->joinSub($this->rows($attribute), 'root_value', 'root_value.product_id', '=', 'inheriting_sku.parent_product_id')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->fromSub($this->rows($attribute), 'own_value')
                ->whereColumn('own_value.product_id', 'inheriting_sku.id'))
            ->selectRaw('inheriting_sku.id as sku_id, root_value.attribute_value_id, root_value.custom_value');

        return $direct->unionAll($inherited);
    }

    private function numericRows(Attribute $attribute): \Illuminate\Database\Query\Builder
    {
        $raw = DB::query()->fromSub($this->effectiveRows($attribute), 'effective')
            ->leftJoin('product_attribute_values as numeric_value', 'numeric_value.id', '=', 'effective.attribute_value_id')
            ->selectRaw("effective.sku_id, REPLACE(TRIM(COALESCE(numeric_value.value, effective.custom_value)), ',', '.') as raw_mm");
        $valid = DB::query()->fromSub($raw, 'raw_dimension');
        if (DB::connection()->getDriverName() === 'sqlite') {
            $valid->whereRaw("raw_mm != '' AND raw_mm NOT LIKE '.%' AND raw_mm NOT LIKE '%.' AND raw_mm NOT GLOB '*[^0-9.]*' AND raw_mm GLOB '*[0-9]*' AND LENGTH(raw_mm) - LENGTH(REPLACE(raw_mm, '.', '')) <= 1");
        } else {
            $valid->whereRaw("raw_mm REGEXP '^[0-9]+([.][0-9]+)?$'");
        }

        return $valid->selectRaw('sku_id, CAST(raw_mm AS DECIMAL(15,3)) as mm')->whereRaw('CAST(raw_mm AS DECIMAL(15,3)) > 0');
    }

    public function meta(Builder $roots, array $input, array $mandatory = []): array
    {
        $meta = ['price' => ['min' => 0, 'max' => 0], 'colors' => [], 'sizes' => [],
            'attributes' => [], 'manufacturers' => [], 'dimensions' => [], 'facet_mode' => 'excluding_self'];
        $priceSkus = $this->skus($roots, $this->filters($input, $mandatory, 'price'));
        $meta['price'] = ['min' => (float) ((clone $priceSkus)->min('catalog_prices.effective_price') ?? 0),
            'max' => (float) ((clone $priceSkus)->max('catalog_prices.effective_price') ?? 0)];
        $meta['has_on_sale'] = $this->skus($roots, $this->filters($input, $mandatory, 'on_sale'))
            ->whereColumn('catalog_prices.reference_price', '>', 'catalog_prices.effective_price')->exists();
        $meta['has_sale'] = $meta['has_on_sale'];
        foreach (self::DIMENSIONS as $key => $slug) {
            $attribute = $this->attribute($slug);
            $range = ['min' => null, 'max' => null, 'unit' => 'mm'];
            if ($attribute) {
                $skus = $this->skus($roots, $this->filters($input, $mandatory, $key))->select('products.id');
                $numbers = DB::query()->fromSub($this->numericRows($attribute), 'numbers')->whereIn('sku_id', $skus);
                $range['min'] = (clone $numbers)->min('mm');
                $range['max'] = (clone $numbers)->max('mm');
                foreach (['min', 'max'] as $bound) {
                    $range[$bound] = $range[$bound] === null ? null : (float) $range[$bound];
                }
            }
            $meta['dimensions'][$key] = $range;
        }
        $rootIds = (clone $roots)->reorder()->select('products.id');
        $scopeIds = Product::query()->active()->where(fn ($q) => $q->whereIn('id', clone $rootIds)
            ->orWhereIn('parent_product_id', clone $rootIds))->select('id');
        $usedAttributes = DB::table('product_product_attributes')->whereIn('product_id', clone $scopeIds)->select('attribute_id')
            ->union(DB::table('product_variant_attributes')->whereIn('product_id', clone $scopeIds)->select('attribute_id'));
        foreach (Attribute::whereIn('id', $usedAttributes)->where(fn ($q) => $q->where('is_filterable', true)
            ->orWhereIn('slug', [Attribute::SLUG_COLOR, Attribute::SLUG_SIZE]))->orderBy('sort_order')->get() as $attribute) {
            $this->attributesBySlug[$attribute->slug] = $attribute;
            if (in_array($attribute->slug, self::DIMENSIONS, true)) {
                continue; // Numeric dimensions have a separate range contract.
            }
            $skus = $this->skus($roots, $this->filters($input, $mandatory, $attribute->slug))->select('products.id');
            $values = DB::query()->fromSub($this->effectiveRows($attribute), 'facet')
                ->join('products as sku', 'sku.id', '=', 'facet.sku_id')
                ->leftJoin('product_attribute_values as val', 'val.id', '=', 'facet.attribute_value_id')
                ->whereIn('facet.sku_id', $skus)
                ->selectRaw('facet.attribute_value_id, facet.custom_value, val.value, val.slug, val.color_code, COUNT(DISTINCT COALESCE(sku.parent_product_id, sku.id)) as product_count')
                ->groupBy('facet.attribute_value_id', 'facet.custom_value', 'val.value', 'val.slug', 'val.color_code')
                ->get()->map(function ($v) {
                    $name = trim((string) ($v->value ?? $v->custom_value));

                    return ['id' => $v->attribute_value_id, 'name' => $name, 'value' => $name,
                        'slug' => $v->slug ?: (Str::slug($name) ?: 'value-'.substr(sha1(mb_strtolower($name)), 0, 12)),
                        'code' => $v->color_code, 'count' => (int) $v->product_count];
                })->filter(fn ($v) => $v['name'] !== '')->sortBy('name')->values()->all();
            if ($attribute->slug === Attribute::SLUG_COLOR) {
                $meta['colors'] = $values;
            } elseif ($attribute->slug === Attribute::SLUG_SIZE) {
                $meta['sizes'] = $values;
            } elseif ($values !== []) {
                $meta['attributes'][] = ['id' => $attribute->id, 'name' => $attribute->name,
                    'slug' => $attribute->slug, 'values' => $values];
            }
        }

        $filteredRootIds = $this->apply(clone $roots, $this->filters($input, $mandatory))->select('products.id');
        $meta['manufacturers'] = \App\Models\Product\Manufacturer::query()
            ->whereHas('products', fn ($q) => $q->whereIn('products.id', $filteredRootIds))
            ->orderBy('name')->get()->map(fn ($m) => ['id' => $m->id, 'name' => $m->name,
                'slug' => $m->slug ?? Str::slug($m->name)])->all();

        return $meta;
    }
}
