<?php

namespace App\Actions\Product\Data;

/**
 * Автоматически собранная общая карточка для объединяемых товаров.
 */
final class MergedProductDraft
{
    /**
     * @param  array<int, string>  $variantLabels  product_id => название вариации
     * @param  list<int>  $taxonIds  объединение категорий всех товаров
     * @param  list<array{name: string, product_ids: list<int>}>  $categoryBreakdown  категория => товары в ней
     * @param  array<int, array{attribute_value_id: int|null, custom_value: string|null}>  $commonAttributes  attribute_id => значение
     * @param  list<array{name: string, values: array<int, string>}>  $differingAttributes  значения по product_id
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $name,
        public array $variantLabels,
        public array $taxonIds,
        public array $categoryBreakdown,
        public bool $categoriesDiffer,
        public array $commonAttributes,
        public array $differingAttributes,
        public ?string $description,
        public ?int $imageSourceProductId,
        public ?int $manufacturerId,
        public array $warnings,
    ) {
    }
}
