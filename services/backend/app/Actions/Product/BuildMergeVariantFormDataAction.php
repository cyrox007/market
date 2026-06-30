<?php

namespace App\Actions\Product;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use Illuminate\Support\Facades\Log;

/**
 * Формирует данные атрибутов торгового предложения при объединении простых товаров.
 * Модель как в Битрикс: родитель — товар, дочерние — SKU/ТП с отличительными свойствами.
 * Главное отличие — атрибут «Вариант» (название предложения); остальные — из характеристик карточки.
 */
class BuildMergeVariantFormDataAction
{
    public function __construct(
        protected GetVariationAttributesForProductAction $getVariationAttributesForProductAction,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(Product $parent, Product $variant, ?string $variantLabelOverride = null): array
    {
        $data = [];
        $variantAttribute = Attribute::ensureVariantAttribute();
        $label = $this->resolveOfferLabel($variant, $variantLabelOverride);
        $data['variation_custom_' . $variantAttribute->id] = $label;

        $variationAttributes = $this->getVariationAttributesForProductAction->execute($parent);
        $variant->loadMissing('attributes');

        foreach ($variationAttributes as $attribute) {
            if ($variantAttribute && (int) $attribute->id === (int) $variantAttribute->id) {
                continue;
            }

            $pivotRow = $variant->attributes()->where('product_attributes.id', $attribute->id)->first();
            if ($pivotRow === null || $pivotRow->pivot === null) {
                continue;
            }

            $custom = $pivotRow->pivot->custom_value ?? null;
            $valueId = $pivotRow->pivot->attribute_value_id ?? null;

            if ($custom !== null && $custom !== '') {
                $data['variation_custom_' . $attribute->id] = (string) $custom;
            } elseif ($valueId !== null && $valueId !== '') {
                $data['variation_attr_' . $attribute->id] = $valueId;
            }
        }

        Log::debug('[BuildMergeVariantFormData] built', [
            'parent_id' => $parent->getKey(),
            'variant_id' => $variant->getKey(),
            'keys' => array_keys($data),
        ]);

        return $data;
    }

    public function resolveOfferLabel(Product $product, ?string $override = null): string
    {
        $override = trim((string) ($override ?? ''));
        if ($override !== '') {
            return $override;
        }

        $name = trim((string) $product->name);
        if ($name !== '') {
            return $name;
        }

        return trim((string) $product->sku) !== '' ? trim((string) $product->sku) : 'Вариант #' . $product->getKey();
    }
}
