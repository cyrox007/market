<?php

namespace App\Services\Product;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductVariationAttributeService
{
    /**
     * Canonical commercial sizes. Physical dimensions are deliberately ignored.
     *
     * @return Collection<int, object>
     */
    public function sizes(Product $product): Collection
    {
        if ($product->isVariable()) {
            return $product->getAvailableValuesForVariationAttribute(Attribute::SLUG_SIZE);
        }

        return $this->valuesForSimpleProduct($product, Attribute::SLUG_SIZE);
    }

    /**
     * Canonical values stored directly on a non-variable product.
     *
     * @return Collection<int, object>
     */
    public function valuesForSimpleProduct(Product $product, string $slug): Collection
    {
        $attribute = Attribute::query()->where('slug', $slug)->first();
        if (! $attribute) {
            return collect();
        }

        return $this->simpleProductAttributeValues($product, $slug);
    }

    /**
     * Return selected size for a concrete variant in a compatibility-friendly shape.
     *
     * @return array{size:?array}
     */
    public function selectedForVariant(Product $variant): array
    {
        $selected = collect($variant->getVariationAttributesForApi())
            ->keyBy('attribute_slug');

        $size = $selected->get(Attribute::SLUG_SIZE);

        return [
            'size' => $size ? [
                'id' => null,
                'name' => $size['value_name'],
                'slug' => $size['value_slug'],
                'value' => $size['value_name'],
            ] : null,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function simpleProductAttributeValues(Product $product, string $slug): Collection
    {
        $product->loadMissing('attributes.values');
        $rows = $product->getRelation('attributes')->where('slug', $slug);

        return $rows->map(function ($attribute) {
            $pivot = $attribute->pivot;

            if ($pivot?->custom_value !== null && $pivot->custom_value !== '') {
                return (object) [
                    'id' => null,
                    'value' => $pivot->custom_value,
                    'name' => $pivot->custom_value,
                    'slug' => Str::slug($pivot->custom_value),
                ];
            }

            $value = $attribute->values->firstWhere('id', $pivot?->attribute_value_id);
            if (! $value) {
                return null;
            }

            return (object) [
                'id' => $value->id,
                'value' => $value->value,
                'name' => $value->value,
                'slug' => $value->slug,
            ];
        })->filter()->values();
    }
}
