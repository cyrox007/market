<?php

namespace App\Services\Product;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductVariationAttributeService
{
    /**
     * Canonical palette values for API compatibility fields.
     *
     * @return Collection<int, object>
     */
    public function colors(Product $product): Collection
    {
        if ($product->isVariable()) {
            return $product->getAvailableValuesForVariationAttribute(Attribute::SLUG_COLOR);
        }

        return $this->valuesForSimpleProduct($product, Attribute::SLUG_COLOR);
    }

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

        return $this->simpleProductAttributeValues($product, $slug, $attribute->type === 'color');
    }

    /**
     * Return selected color/size for a concrete variant in a compatibility-friendly shape.
     *
     * @return array{color:?array,size:?array}
     */
    public function selectedForVariant(Product $variant): array
    {
        $selected = collect($variant->getVariationAttributesForApi())
            ->keyBy('attribute_slug');

        $color = $selected->get(Attribute::SLUG_COLOR);
        $size = $selected->get(Attribute::SLUG_SIZE);

        return [
            'color' => $color ? [
                'id' => null,
                'name' => $color['value_name'],
                'slug' => $color['value_slug'],
                'code' => $color['code'] ?? null,
            ] : null,
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
    private function simpleProductAttributeValues(Product $product, string $slug, bool $withColorCode): Collection
    {
        $rows = $product->attributes()
            ->where('product_attributes.slug', $slug)
            ->with('values')
            ->get();

        return $rows->map(function ($attribute) use ($withColorCode) {
            $pivot = $attribute->pivot;

            if ($pivot?->custom_value !== null && $pivot->custom_value !== '') {
                return (object) [
                    'id' => null,
                    'value' => $pivot->custom_value,
                    'name' => $pivot->custom_value,
                    'slug' => Str::slug($pivot->custom_value),
                    'color_code' => null,
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
                'color_code' => $withColorCode ? $value->color_code : null,
            ];
        })->filter()->values();
    }
}
