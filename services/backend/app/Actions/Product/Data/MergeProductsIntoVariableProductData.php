<?php

namespace App\Actions\Product\Data;

final class MergeProductsIntoVariableProductData
{
    /**
     * @param  list<int>  $productIds  Все выбранные товары (включая родителя)
     * @param  array<int, string>  $variantLabelOverrides  product_id => название вариации (атрибут «Вариант»); пусто = авто из названия/SKU
     */
    public function __construct(
        public int $parentId,
        public array $productIds,
        public array $variantLabelOverrides = [],
        public bool $mergeCategories = false,
        public ?string $parentName = null,
    ) {
    }
}
