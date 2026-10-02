<?php

namespace App\Actions\Product\Data;

final class MergeProductsIntoVariableProductData
{
    /**
     * @param  list<int>  $productIds  Все выбранные товары — каждый станет торговым предложением
     * @param  array<int, string>  $variantLabelOverrides  product_id => название вариации (атрибут «Вариант»); пусто = авто
     * @param  string|null  $name  Название общей карточки; пусто = авто из названий товаров
     */
    public function __construct(
        public array $productIds,
        public array $variantLabelOverrides = [],
        public ?string $name = null,
    ) {
    }
}
