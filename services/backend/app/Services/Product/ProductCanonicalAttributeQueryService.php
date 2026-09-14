<?php

namespace App\Services\Product;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCanonicalAttributeQueryService
{
    /**
     * Apply a canonical attribute filter to parent products.
     *
     * The value may live directly on a simple product in product_product_attributes
     * or on one of a variable product's active variants in product_variant_attributes.
     * Both predefined values and custom_value are supported.
     *
     * @param array<int, string> $inputs
     */
    public function applyFilter(Builder $query, string $attributeSlug, array $inputs): Builder
    {
        $inputs = $this->normalizeInputs($inputs);
        if ($inputs === []) {
            return $query;
        }

        $attribute = Attribute::query()->where('slug', $attributeSlug)->first();
        if (! $attribute) {
            return $query->whereRaw('1 = 0');
        }

        [$valueIds, $customValues] = $this->resolveMatches($attribute, $inputs);
        if ($valueIds->isEmpty() && $customValues->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $root) use ($attribute, $valueIds, $customValues) {
            $root->whereExists(function ($sub) use ($attribute, $valueIds, $customValues) {
                $sub->selectRaw('1')
                    ->from('product_product_attributes as ppa')
                    ->whereColumn('ppa.product_id', 'products.id')
                    ->where('ppa.attribute_id', $attribute->id)
                    ->where(fn ($values) => $this->applyResolvedValueCondition($values, 'ppa', $valueIds, $customValues));
            })->orWhereExists(function ($sub) use ($attribute, $valueIds, $customValues) {
                $sub->selectRaw('1')
                    ->from('products as canonical_variants')
                    ->join('product_variant_attributes as pva', 'pva.product_id', '=', 'canonical_variants.id')
                    ->whereColumn('canonical_variants.parent_product_id', 'products.id')
                    ->where('canonical_variants.state', Product::ACTIVE)
                    ->where('pva.attribute_id', $attribute->id)
                    ->where(fn ($values) => $this->applyResolvedValueCondition($values, 'pva', $valueIds, $customValues));
            });
        });
    }

    /**
     * Build filter metadata for one canonical attribute inside the supplied parent-product scope.
     * Physical product dimensions are deliberately not involved.
     *
     * @return Collection<int, array{id:int|null,name:string,slug:string,value:string,code:string|null,count:int}>
     */
    public function buildFilterMeta(Builder $baseQuery, string $attributeSlug): Collection
    {
        $attribute = Attribute::query()->where('slug', $attributeSlug)->first();
        if (! $attribute) {
            return collect();
        }

        $parentIds = fn () => (clone $baseQuery)->select('products.id');

        $regularRows = DB::table('product_product_attributes as ppa')
            ->join('products as p', 'p.id', '=', 'ppa.product_id')
            ->leftJoin('product_attribute_values as pav', 'pav.id', '=', 'ppa.attribute_value_id')
            ->where('ppa.attribute_id', $attribute->id)
            ->whereIn('p.id', $parentIds())
            ->selectRaw('p.id as root_product_id, ppa.attribute_value_id, ppa.custom_value, pav.value, pav.slug, pav.color_code')
            ->get();

        $variantRows = DB::table('product_variant_attributes as pva')
            ->join('products as v', 'v.id', '=', 'pva.product_id')
            ->leftJoin('product_attribute_values as pav', 'pav.id', '=', 'pva.attribute_value_id')
            ->where('pva.attribute_id', $attribute->id)
            ->where('v.state', Product::ACTIVE)
            ->whereIn('v.parent_product_id', $parentIds())
            ->selectRaw('v.parent_product_id as root_product_id, pva.attribute_value_id, pva.custom_value, pav.value, pav.slug, pav.color_code')
            ->get();

        $grouped = [];
        foreach ($regularRows->concat($variantRows) as $row) {
            $resolved = $this->resolveRowValue($row);
            if ($resolved === null) {
                continue;
            }

            $key = $resolved['key'];
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'id' => $resolved['id'],
                    'name' => $resolved['name'],
                    'slug' => $resolved['slug'],
                    'value' => $resolved['name'],
                    'code' => $attribute->type === 'color' ? $resolved['code'] : null,
                    'products' => [],
                ];
            }

            $grouped[$key]['products'][(int) $row->root_product_id] = true;
        }

        return collect($grouped)
            ->map(function (array $entry) {
                $entry['count'] = count($entry['products']);
                unset($entry['products']);

                return $entry;
            })
            ->sortBy(fn (array $entry) => mb_strtolower($entry['name']))
            ->values();
    }

    /**
     * Return canonical values of one variation attribute, optionally constrained by other
     * variation attributes (for example: sizes available for a selected color).
     *
     * @param array<string, string> $constraints
     * @return Collection<int, object>
     */
    public function availableVariationValues(Product $product, string $targetSlug, array $constraints = []): Collection
    {
        if (! $product->isVariable()) {
            return app(ProductVariationAttributeService::class)->valuesForSimpleProduct($product, $targetSlug);
        }

        $target = Attribute::query()
            ->where('slug', $targetSlug)
            ->where('is_use_in_variations', true)
            ->first();
        if (! $target) {
            return collect();
        }

        $variantIds = $product->variants()->active()->pluck('id');
        if ($variantIds->isEmpty()) {
            return collect();
        }

        foreach ($constraints as $slug => $input) {
            $input = trim((string) $input);
            if ($input === '') {
                continue;
            }

            $constraint = Attribute::query()
                ->where('slug', $slug)
                ->where('is_use_in_variations', true)
                ->first();
            if (! $constraint) {
                return collect();
            }

            [$valueIds, $customValues] = $this->resolveMatches($constraint, [$input]);
            if ($valueIds->isEmpty() && $customValues->isEmpty()) {
                return collect();
            }

            $matchingIds = DB::table('product_variant_attributes as pva')
                ->whereIn('pva.product_id', $variantIds)
                ->where('pva.attribute_id', $constraint->id)
                ->where(fn ($values) => $this->applyResolvedValueCondition($values, 'pva', $valueIds, $customValues))
                ->pluck('pva.product_id');

            $variantIds = $variantIds->intersect($matchingIds)->values();
            if ($variantIds->isEmpty()) {
                return collect();
            }
        }

        $rows = DB::table('product_variant_attributes as pva')
            ->leftJoin('product_attribute_values as pav', 'pav.id', '=', 'pva.attribute_value_id')
            ->whereIn('pva.product_id', $variantIds)
            ->where('pva.attribute_id', $target->id)
            ->select('pva.attribute_value_id', 'pva.custom_value', 'pav.value', 'pav.slug', 'pav.color_code')
            ->get();

        return $rows
            ->map(function ($row) use ($target) {
                $resolved = $this->resolveRowValue($row);
                if ($resolved === null) {
                    return null;
                }

                return (object) [
                    'id' => $resolved['id'],
                    'value' => $resolved['name'],
                    'name' => $resolved['name'],
                    'slug' => $resolved['slug'],
                    'color_code' => $target->type === 'color' ? $resolved['code'] : null,
                ];
            })
            ->filter()
            ->unique(fn ($value) => ($value->id !== null ? 'id:' . $value->id : 'custom:' . $value->slug))
            ->values();
    }

    /**
     * @param array<int, string> $inputs
     * @return array{0: Collection<int, int>, 1: Collection<int, string>}
     */
    private function resolveMatches(Attribute $attribute, array $inputs): array
    {
        $inputs = $this->normalizeInputs($inputs);
        $tokens = collect($inputs)
            ->flatMap(fn (string $value) => [$this->normalizeComparable($value), Str::slug($value)])
            ->filter()
            ->unique()
            ->values();

        $values = AttributeValue::query()
            ->where('attribute_id', $attribute->id)
            ->get();

        $valueIds = $values
            ->filter(function (AttributeValue $value) use ($tokens) {
                return $tokens->contains($this->normalizeComparable((string) $value->value))
                    || $tokens->contains($this->normalizeComparable((string) $value->slug))
                    || $tokens->contains(Str::slug((string) $value->value));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $customCandidates = DB::table('product_product_attributes')
            ->where('attribute_id', $attribute->id)
            ->whereNotNull('custom_value')
            ->where('custom_value', '!=', '')
            ->pluck('custom_value')
            ->concat(
                DB::table('product_variant_attributes')
                    ->where('attribute_id', $attribute->id)
                    ->whereNotNull('custom_value')
                    ->where('custom_value', '!=', '')
                    ->pluck('custom_value')
            )
            ->unique()
            ->values();

        $customValues = $customCandidates
            ->filter(function ($value) use ($tokens) {
                return $tokens->contains($this->normalizeComparable((string) $value))
                    || $tokens->contains(Str::slug((string) $value));
            })
            ->map(fn ($value) => (string) $value)
            ->values();

        return [$valueIds, $customValues];
    }

    private function applyResolvedValueCondition($query, string $alias, Collection $valueIds, Collection $customValues): void
    {
        if ($valueIds->isNotEmpty()) {
            $query->whereIn("{$alias}.attribute_value_id", $valueIds->all());
        }

        if ($customValues->isNotEmpty()) {
            $method = $valueIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
            $query->{$method}("{$alias}.custom_value", $customValues->all());
        }
    }

    /**
     * @return array{key:string,id:int|null,name:string,slug:string,code:string|null}|null
     */
    private function resolveRowValue(object $row): ?array
    {
        if ($row->attribute_value_id !== null && $row->value !== null && $row->value !== '') {
            $name = (string) $row->value;
            $slug = (string) ($row->slug ?: Str::slug($name));

            return [
                'key' => 'id:' . (int) $row->attribute_value_id,
                'id' => (int) $row->attribute_value_id,
                'name' => $name,
                'slug' => $slug,
                'code' => $row->color_code !== null ? (string) $row->color_code : null,
            ];
        }

        $custom = trim((string) ($row->custom_value ?? ''));
        if ($custom === '') {
            return null;
        }

        $slug = Str::slug($custom);
        if ($slug === '') {
            $slug = 'value-' . substr(sha1(mb_strtolower($custom)), 0, 12);
        }

        return [
            'key' => 'custom:' . mb_strtolower($custom),
            'id' => null,
            'name' => $custom,
            'slug' => $slug,
            'code' => null,
        ];
    }

    /**
     * @param array<int, string> $inputs
     * @return array<int, string>
     */
    private function normalizeInputs(array $inputs): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($value) => trim((string) $value),
            $inputs,
        ), fn (string $value) => $value !== '')));
    }

    private function normalizeComparable(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
