<?php

namespace App\Actions\Product;

use App\Models\Product\Attribute;
use App\Models\Product\Category;
use App\Models\Product\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GetVariationAttributesForProductAction
{
    /**
     * @param  list<int>|null  $forcedAttributeIds  Явный список атрибутов (например из bulk merge)
     * @return Collection<int, Attribute>
     */
    public function execute(Product $parentProduct, ?array $forcedAttributeIds = null): Collection
    {
        if ($parentProduct->isVariant()) {
            Log::debug('[GetVariationAttributesForProduct] skipped variant product', [
                'product_id' => $parentProduct->getKey(),
            ]);

            return collect();
        }

        if ($forcedAttributeIds !== null && $forcedAttributeIds !== []) {
            $attributes = Attribute::query()
                ->whereIn('id', $forcedAttributeIds)
                ->with('orderedValues')
                ->get();

            Log::debug('[GetVariationAttributesForProduct] forced selection', [
                'product_id' => $parentProduct->getKey(),
                'source' => 'forced',
                'count' => $attributes->count(),
            ]);

            return $this->ensureVariantAttributePresent($attributes);
        }

        $selected = $parentProduct->variationAttributeSelection()->with('orderedValues')->get();
        if ($selected->isNotEmpty()) {
            Log::debug('[GetVariationAttributesForProduct] product selection', [
                'product_id' => $parentProduct->getKey(),
                'source' => 'selection',
                'count' => $selected->count(),
            ]);

            return $this->ensureVariantAttributePresent($selected);
        }

        $categoryAttributes = $this->getCategoryVariationAttributes($parentProduct);
        if ($categoryAttributes->isNotEmpty()) {
            Log::debug('[GetVariationAttributesForProduct] category attributes', [
                'product_id' => $parentProduct->getKey(),
                'source' => 'category',
                'count' => $categoryAttributes->count(),
            ]);

            return $this->ensureVariantAttributePresent($categoryAttributes);
        }

        $global = Attribute::variationAttributes()->with('orderedValues')->get();

        Log::debug('[GetVariationAttributesForProduct] global attributes', [
            'product_id' => $parentProduct->getKey(),
            'source' => 'global',
            'count' => $global->count(),
        ]);

        return $this->ensureVariantAttributePresent($global);
    }

    /**
     * @return Collection<int, Attribute>
     */
    protected function getCategoryVariationAttributes(Product $product): Collection
    {
        $taxon = $product->category();
        if (! $taxon) {
            return collect();
        }

        $category = $taxon instanceof Category
            ? $taxon
            : Category::find($taxon->id);

        if (! $category) {
            return collect();
        }

        $currentCategory = $category;
        while ($currentCategory) {
            if (! $currentCategory->relationLoaded('variationAttributes')) {
                $currentCategory->load('variationAttributes');
            }

            $attrs = $currentCategory->variationAttributes()->with('orderedValues')->get();
            if ($attrs->isNotEmpty()) {
                return $attrs;
            }

            if (! $currentCategory->relationLoaded('parent') && $currentCategory->parent_id) {
                $currentCategory->load('parent');
            }
            $currentCategory = $currentCategory->parent;
        }

        return collect();
    }

    /**
     * @param  Collection<int, Attribute>  $attributes
     * @return Collection<int, Attribute>
     */
    protected function ensureVariantAttributePresent(Collection $attributes): Collection
    {
        $variant = Attribute::ensureVariantAttribute();

        if ($attributes->contains('id', $variant->id)) {
            return $attributes;
        }

        $variant->loadMissing('orderedValues');

        return collect([$variant])->concat($attributes)->values();
    }
}
