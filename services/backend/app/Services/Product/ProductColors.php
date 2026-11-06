<?php

namespace App\Services\Product;

use App\Models\Product\Color\ColorSolution;
use App\Models\Product\Color\ProductColorRow;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/** Цвета товара для ответа API: цвет каждого элемента, палитра вариаций, варианты выбора по цвету. */
class ProductColors
{
    /** Ключ строки без элемента — основной цвет. */
    public const MAIN = 'main';

    public const EAGER = ['colorRows.element', 'colorRows.solution.baseColors', 'colorRows.solution.media'];

    /**
     * Подгрузить цвета для набора товаров одним пакетом; у вариативных — цвета уже загруженных вариаций.
     *
     * @param  iterable<Product>  $products
     */
    public function preload(iterable $products): void
    {
        $products = new EloquentCollection(collect($products)->filter(fn ($product) => $product instanceof Product)->values()->all());
        $products->loadMissing(self::EAGER);
        $variants = $products->filter(fn (Product $product) => $product->relationLoaded('variants'))->flatMap->variants;
        (new EloquentCollection($variants->all()))->loadMissing(self::EAGER);
    }

    /**
     * Prepare one swatch palette per parent group for catalog SKU cards.
     * Fetch siblings in batches, including offers on other pagination pages.
     * Selected colors always belong to the actual SKU, never its siblings.
     */
    public function preloadCatalogPalettes(iterable $products): void
    {
        $items = collect($products)->filter(fn ($product) => $product instanceof Product);
        $groupIds = $items->map(fn (Product $product) => $product->isVariant()
            ? (int) $product->parent_product_id
            : ($product->isVariable() ? (int) $product->id : null))
            ->filter()->unique()->values()->all();

        $palettes = [];
        if ($groupIds !== []) {
            $parents = Product::query()->active()->whereKey($groupIds)->get()->keyBy('id');
            $siblings = Product::query()->active()->whereIn('parent_product_id', $groupIds)
                ->select(['id', 'parent_product_id', 'state'])
                ->with(self::EAGER)->orderBy('id')->get()->groupBy('parent_product_id');

            foreach ($groupIds as $groupId) {
                $parent = $parents->get($groupId);
                $variants = $siblings->get($groupId, new EloquentCollection());
                $palettes[$groupId] = $parent && $parent->isVariable()
                    ? $this->palette($parent, $variants)
                    : [];
            }
        }

        foreach ($items as $product) {
            $groupId = $product->isVariant() ? (int) $product->parent_product_id
                : ($product->isVariable() ? (int) $product->id : 0);
            // Keep the batch result on the SKU itself; multiple collections
            // serialized in one response must not overwrite each other's map.
            $product->setRelation('catalogColorPalette', $palettes[$groupId] ?? []);
        }
    }

    /** Цвета самого товара или вариации. */
    public function rows(Product $product): array
    {
        $product->loadMissing(self::EAGER);

        return $product->colorRows->map(fn (ProductColorRow $row) => [
            'element' => $this->element($row),
            'solution' => $this->solution($row->solution),
        ])->values()->all();
    }

    /** Палитра вариативного товара: решения каждого элемента по всем активным вариациям. */
    public function palette(Product $product, ?Collection $variants = null): array
    {
        if (! $product->isVariable()) {
            return [];
        }

        $groups = [];
        foreach ($this->variants($product, $variants) as $variant) {
            foreach ($variant->colorRows as $row) {
                $key = $this->rowKey($row);
                $groups[$key] ??= ['element' => $this->element($row), 'solutions' => []];
                $groups[$key]['solutions'][$row->solution->id] ??= $this->solution($row->solution);
            }
        }

        return array_values(array_map(fn (array $group) => [...$group, 'solutions' => array_values($group['solutions'])], $groups));
    }

    /** Выбор вариации по цвету: группа на каждый элемент, по которому вариации различаются. */
    public function options(Product $product, ?Collection $variants = null): array
    {
        if (! $product->isVariable()) {
            return [];
        }

        $groups = [];
        foreach ($this->variants($product, $variants) as $variant) {
            foreach ($variant->colorRows as $row) {
                $key = $this->rowKey($row);
                $optionKey = $row->solution->slug;
                $groups[$key] ??= ['key' => $key, 'element' => $this->element($row), 'options' => []];
                $groups[$key]['options'][$optionKey] ??= [
                    'key' => $optionKey,
                    'solution' => $this->solution($row->solution),
                    'variant_ids' => [],
                ];
                $groups[$key]['options'][$optionKey]['variant_ids'][] = (int) $variant->id;
            }
        }

        return collect($groups)
            ->filter(fn (array $group) => count($group['options']) > 1)
            ->map(fn (array $group) => [...$group, 'options' => array_values($group['options'])])
            ->values()->all();
    }

    /** Выбранные варианты цвета конкретной вариации: ключ группы → ключ варианта. */
    public function selected(Product $variant): array
    {
        $variant->loadMissing(self::EAGER);

        return $variant->colorRows
            ->mapWithKeys(fn (ProductColorRow $row) => [$this->rowKey($row) => $row->solution->slug])
            ->all();
    }

    private function variants(Product $product, ?Collection $variants = null): Collection
    {
        $variants ??= $product->relationLoaded('variants') ? $product->variants : $product->variants()->active()->get();
        $variants = $variants->filter(fn (Product $variant) => $variant->isActive())->values();

        return $variants->loadMissing(self::EAGER);
    }

    private function rowKey(ProductColorRow $row): string
    {
        return $row->element?->slug ?? self::MAIN;
    }

    private function element(ProductColorRow $row): ?array
    {
        return $row->element ? [
            'id' => $row->element->id,
            'name' => $row->element->name,
            'slug' => $row->element->slug,
        ] : null;
    }

    private function solution(ColorSolution $solution): array
    {
        return [
            'id' => $solution->id,
            'name' => $solution->name,
            'slug' => $solution->slug,
            'icon_url' => $solution->getFirstMediaUrl('icon') ?: null,
            'base_colors' => $solution->baseColors->map(fn ($color) => [
                'id' => $color->id,
                'name' => $color->name,
                'slug' => $color->slug,
                'hex' => $color->hex,
            ])->values()->all(),
        ];
    }
}
