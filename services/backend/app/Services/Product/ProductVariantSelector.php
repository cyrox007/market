<?php

namespace App\Services\Product;

use App\Models\Product\Product;

/** Вариация по выбору покупателя: характеристики и цвет (карточка, корзина). */
class ProductVariantSelector
{
    public function __construct(private readonly ProductColors $colors)
    {
    }

    /**
     * Выбор цвета из запроса: ключ группы → ключ варианта.
     *
     * @return array<string, string>
     */
    public static function colorSelection(mixed $input): array
    {
        $selection = is_array($input)
            ? array_filter(array_map(fn ($value) => is_string($value) ? trim($value) : '', $input), fn ($value) => $value !== '')
            : [];
        ksort($selection);

        return $selection;
    }

    /**
     * Вариация по характеристикам и цвету; null — подходящей нет.
     *
     * @param  array<string, string>  $attributes  slug характеристики → значение
     * @param  array<string, string>  $colorOptions  ключ группы цвета → ключ варианта
     */
    public function variant(Product $product, array $attributes, array $colorOptions): ?Product
    {
        $ids = $colorOptions === [] ? null : $this->idsByColor($product, $colorOptions);
        if ($ids === []) {
            return null;
        }
        if ($attributes === []) {
            return $ids === null ? null : Product::find($ids[0]);
        }

        return $product->getVariantByVariationAttributes($attributes, $ids);
    }

    /**
     * @param  array<string, string>  $selection
     * @return int[]
     */
    private function idsByColor(Product $product, array $selection): array
    {
        // Цвета читаются отдельной выборкой: кэш карточки хранит вариации только с их полями.
        return $product->variants()->active()->get()->loadMissing(ProductColors::EAGER)
            ->filter(function (Product $variant) use ($selection): bool {
                $selected = $this->colors->selected($variant);
                foreach ($selection as $group => $option) {
                    if (($selected[$group] ?? null) !== $option) {
                        return false;
                    }
                }

                return true;
            })
            ->map(fn (Product $variant) => (int) $variant->id)
            ->values()->all();
    }
}
