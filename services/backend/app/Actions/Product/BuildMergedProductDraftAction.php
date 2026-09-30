<?php

namespace App\Actions\Product;

use App\Actions\Product\Data\MergedProductDraft;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use Illuminate\Support\Collection;

/**
 * Собирает общую карточку из объединяемых товаров: название, подписи вариаций,
 * категории, совпадающие характеристики, описание, фото и производителя.
 */
class BuildMergedProductDraftAction
{
    /** Код номенклатуры 1С в начале названия: «001.007.003 », «001.005.203с ». */
    protected const ONE_C_CODE_PREFIX = '/^\d{3}\.\d{3}\.\d{3}\S*\s+/u';

    /**
     * @param  Collection<int, Product>  $products  в порядке выбора
     */
    public function execute(Collection $products): MergedProductDraft
    {
        $products = $products->values();
        $products->each(fn (Product $product) => $product->loadMissing(['taxons', 'attributes', 'media']));

        [$name, $variantLabels] = $this->resolveNameAndLabels($products);
        [$taxonIds, $categoryBreakdown, $categoriesDiffer] = $this->resolveCategories($products);
        [$commonAttributes, $differingAttributes] = $this->resolveAttributes($products);

        $warnings = [];

        if ($categoriesDiffer) {
            $warnings[] = 'У товаров разные категории — общая карточка будет добавлена во все: '
                . collect($categoryBreakdown)->pluck('name')->implode(', ') . '.';
        }

        $descriptions = $products
            ->map(fn (Product $product) => trim((string) $product->description))
            ->filter(fn (string $text) => $text !== '')
            ->unique()
            ->values();

        $description = $descriptions->sortByDesc(fn (string $text) => mb_strlen($text))->first();
        if ($descriptions->count() > 1) {
            $warnings[] = 'Описания товаров различаются — взято самое полное, проверьте его в общей карточке.';
        }

        $manufacturerIds = $products->pluck('manufacturer_id')->filter()->unique()->values();
        if ($manufacturerIds->count() > 1) {
            $warnings[] = 'У товаров разные производители — в общей карточке производитель не заполнен.';
        }

        $imageSource = $products->first(fn (Product $product) => $product->getFirstMedia('images') !== null);

        return new MergedProductDraft(
            name: $name,
            variantLabels: $variantLabels,
            taxonIds: $taxonIds,
            categoryBreakdown: $categoryBreakdown,
            categoriesDiffer: $categoriesDiffer,
            commonAttributes: $commonAttributes,
            differingAttributes: $differingAttributes,
            description: $description,
            imageSourceProductId: $imageSource?->id,
            manufacturerId: $manufacturerIds->count() === 1 ? (int) $manufacturerIds->first() : null,
            warnings: $warnings,
        );
    }

    /**
     * Название — общее начало названий без кода 1С, подпись вариации — оставшаяся часть.
     *
     * @param  Collection<int, Product>  $products
     * @return array{0: string, 1: array<int, string>}
     */
    protected function resolveNameAndLabels(Collection $products): array
    {
        $words = $products->mapWithKeys(fn (Product $product) => [
            $product->id => preg_split('/\s+/u', $this->stripOneCCode((string) $product->name), -1, PREG_SPLIT_NO_EMPTY),
        ]);

        $commonLength = 0;
        $first = $words->first();
        while ($commonLength < count($first)) {
            $word = mb_strtolower($first[$commonLength]);
            $allMatch = $words->every(
                fn (array $productWords) => isset($productWords[$commonLength])
                    && mb_strtolower($productWords[$commonLength]) === $word,
            );
            if (! $allMatch) {
                break;
            }
            $commonLength++;
        }

        $name = $commonLength > 0
            ? implode(' ', array_slice($first, 0, $commonLength))
            : implode(' ', $first);

        $labels = $words->map(function (array $productWords, int $productId) use ($commonLength, $products): string {
            $rest = implode(' ', array_slice($productWords, $commonLength));
            if ($rest === '') {
                $rest = implode(' ', $productWords);
            }
            if ($rest === '') {
                $rest = (string) $products->firstWhere('id', $productId)?->sku;
            }

            return mb_strtoupper(mb_substr($rest, 0, 1)) . mb_substr($rest, 1);
        })->all();

        return [$name, $labels];
    }

    /**
     * Подпись вариации для товара, добавляемого в готовую группу: название без кода 1С и без названия карточки.
     */
    public function suggestLabelForParent(Product $parent, Product $product): string
    {
        $name = $this->stripOneCCode((string) $product->name);
        $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $parentWords = preg_split('/\s+/u', $this->stripOneCCode((string) $parent->name), -1, PREG_SPLIT_NO_EMPTY);

        // Сравниваем по словам: «МАРТА-110» не продолжение «МАРТА-11»
        $isPrefix = $parentWords !== []
            && mb_strtolower(implode(' ', array_slice($words, 0, count($parentWords)))) === mb_strtolower(implode(' ', $parentWords));

        $label = $isPrefix ? implode(' ', array_slice($words, count($parentWords))) : $name;

        if ($label === '') {
            $label = $name !== '' ? $name : (string) $product->sku;
        }

        return mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    }

    protected function stripOneCCode(string $name): string
    {
        return trim((string) preg_replace(self::ONE_C_CODE_PREFIX, '', trim($name)));
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array{0: list<int>, 1: list<array{name: string, product_ids: list<int>}>, 2: bool}
     */
    protected function resolveCategories(Collection $products): array
    {
        $breakdown = [];
        $withoutCategory = [];

        foreach ($products as $product) {
            if ($product->taxons->isEmpty()) {
                $withoutCategory[] = $product->id;
            }
            foreach ($product->taxons as $taxon) {
                $breakdown[$taxon->id] ??= ['name' => (string) $taxon->name, 'product_ids' => []];
                $breakdown[$taxon->id]['product_ids'][] = $product->id;
            }
        }

        $taxonIds = array_map('intval', array_keys($breakdown));

        if ($withoutCategory !== [] && $breakdown !== []) {
            $breakdown[] = ['name' => 'Без категории', 'product_ids' => $withoutCategory];
        }

        $distinctSets = $products
            ->map(fn (Product $product) => $product->taxons->pluck('id')->sort()->implode(','))
            ->unique()
            ->count();

        return [$taxonIds, array_values($breakdown), $distinctSets > 1];
    }

    /**
     * Общие — характеристики без расхождений (незаполненное значение расхождением не считается).
     *
     * @param  Collection<int, Product>  $products
     * @return array{0: array<int, array{attribute_value_id: int|null, custom_value: string|null}>, 1: list<array{name: string, values: array<int, string>}>}
     */
    protected function resolveAttributes(Collection $products): array
    {
        $variantAttributeId = Attribute::query()->where('slug', Attribute::SLUG_VARIANT)->value('id');

        $valueIds = $products
            ->flatMap(fn (Product $product) => $product->attributes->pluck('pivot.attribute_value_id'))
            ->filter()
            ->unique();
        $valueNames = AttributeValue::query()->whereIn('id', $valueIds)->pluck('value', 'id');

        /** @var array<int, array{name: string, values: array<int, array{attribute_value_id: int|null, custom_value: string|null, label: string}>}> $byAttribute */
        $byAttribute = [];

        foreach ($products as $product) {
            foreach ($product->attributes as $attribute) {
                if ((int) $attribute->id === (int) $variantAttributeId) {
                    continue;
                }

                $valueId = $attribute->pivot->attribute_value_id !== null ? (int) $attribute->pivot->attribute_value_id : null;
                $custom = $attribute->pivot->custom_value;
                $label = $custom !== null && $custom !== '' ? (string) $custom : (string) ($valueNames[$valueId] ?? '');

                if ($label === '') {
                    continue;
                }

                $byAttribute[$attribute->id] ??= ['name' => (string) $attribute->name, 'values' => []];
                $byAttribute[$attribute->id]['values'][$product->id] = [
                    'attribute_value_id' => $valueId,
                    'custom_value' => $custom !== null && $custom !== '' ? (string) $custom : null,
                    'label' => $label,
                ];
            }
        }

        $common = [];
        $differing = [];

        foreach ($byAttribute as $attributeId => $data) {
            $distinct = collect($data['values'])->map(fn (array $value) => mb_strtolower($value['label']))->unique();

            if ($distinct->count() === 1) {
                $value = collect($data['values'])->first();
                $common[$attributeId] = [
                    'attribute_value_id' => $value['attribute_value_id'],
                    'custom_value' => $value['custom_value'],
                ];

                continue;
            }

            $differing[] = [
                'name' => $data['name'],
                'values' => collect($data['values'])->map(fn (array $value) => $value['label'])->all(),
            ];
        }

        return [$common, $differing];
    }
}
