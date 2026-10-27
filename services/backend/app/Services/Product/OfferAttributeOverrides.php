<?php

namespace App\Services\Product;

use App\Actions\Product\GetVariationAttributesForProductAction;
use App\Models\Product\Attribute;
use App\Models\Product\Product;

/** Optional non-variation properties of an offer, separate from its selection controls. */
class OfferAttributeOverrides
{
    public function ids(Product $parent): array
    {
        $variationIds = app(GetVariationAttributesForProductAction::class)->execute($parent)->pluck('id');

        return Attribute::whereNotIn('id', $variationIds)->where('slug', '!=', Attribute::SLUG_MANUFACTURER)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function formData(Product $offer, Product $parent): array
    {
        return $offer->attributes()->whereIn('product_attributes.id', $this->ids($parent))->get()->groupBy('id')
            ->map(function ($items): array {
                $attribute = $items->first();
                $ids = $items->pluck('pivot.attribute_value_id')->filter()->unique()->values()->all();

                return [
                    'attribute_id' => $attribute->id,
                    'attribute_value_id' => $attribute->is_multiple ? $ids : ($ids[0] ?? null),
                    'custom_value' => $items->pluck('pivot.custom_value')->first(fn ($value) => $value !== null && $value !== ''),
                ];
            })->values()->all();
    }

    public function save(Product $offer, Product $parent, array $rows): void
    {
        app(ProductAttributeSyncService::class)->sync($offer, $rows, $this->ids($parent));
    }
}
