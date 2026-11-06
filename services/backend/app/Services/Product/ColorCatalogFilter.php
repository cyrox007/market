<?php

namespace App\Services\Product;

use App\Models\Product\Color\ColorElement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/** Фильтры каталога по базовым цветам: основной цвет SKU и цвета элементов. */
class ColorCatalogFilter
{
    public const EXCEPT_MAIN = 'colors';

    public const EXCEPT_ELEMENT = 'element_colors:';

    /** @var array<string, int|null> slug элемента → id */
    private array $elementIds = [];

    /**
     * Ключи фильтров цвета; $except исключает собственный выбор фасета.
     *
     * @param  string[]  $colors  slug базовых цветов основного цвета
     * @param  array<string, string[]>  $elementColors  slug элемента → slug базовых цветов
     * @param  string[]|null  $roomColors  ограничение комнаты; null — комната цвета не ограничивает
     * @return array{colors: string[], element_colors: array<string, string[]>, room_colors: string[]|null}
     */
    public function filters(array $colors, array $elementColors, ?array $roomColors, ?string $except): array
    {
        $elementColors = array_filter($elementColors);
        if ($except !== null && str_starts_with($except, self::EXCEPT_ELEMENT)) {
            unset($elementColors[substr($except, strlen(self::EXCEPT_ELEMENT))]);
        }

        return [
            'colors' => $except === self::EXCEPT_MAIN ? [] : $colors,
            'element_colors' => $elementColors,
            'room_colors' => $roomColors,
        ];
    }

    public function hasPredicates(array $filters): bool
    {
        return ! empty($filters['colors']) || ! empty($filters['element_colors']) || isset($filters['room_colors']);
    }

    public function apply(Builder $sku, array $filters): void
    {
        // Цвета комнаты ограничивают выборку всегда, вместе с выбором покупателя.
        if (isset($filters['room_colors'])) {
            $this->whereBaseColors($sku, null, $filters['room_colors']);
        }
        if (! empty($filters['colors'])) {
            $this->whereBaseColors($sku, null, $filters['colors']);
        }
        foreach ($filters['element_colors'] ?? [] as $slug => $values) {
            $elementId = $this->elementIds[$slug] ??= ColorElement::where('slug', $slug)->value('id');
            $elementId ? $this->whereBaseColors($sku, $elementId, $values) : $sku->whereRaw('1 = 0');
        }
    }

    /**
     * Фасеты цвета: `colors` и `color_elements`.
     *
     * @param  callable(string): (array|Builder)  $facetSkus  SKU выборки без собственного выбора фасета
     */
    public function meta(callable $facetSkus): array
    {
        $elements = [];
        foreach (ColorElement::orderBy('sort_order')->orderBy('name')->get() as $element) {
            $values = $this->facet($facetSkus(self::EXCEPT_ELEMENT.$element->slug), $element->id);
            if ($values !== []) {
                $elements[] = ['id' => $element->id, 'name' => $element->name, 'slug' => $element->slug,
                    'filter_name' => $element->filter_name ?: 'Цвет: '.$element->name, 'values' => $values];
            }
        }

        return ['colors' => $this->facet($facetSkus(self::EXCEPT_MAIN), null), 'color_elements' => $elements];
    }

    /** Цвет элемента SKU (null — основной) содержит один из базовых цветов. */
    private function whereBaseColors(Builder $sku, ?int $elementId, array $slugs): void
    {
        if ($slugs === []) {
            $sku->whereRaw('1 = 0');

            return;
        }
        $sku->whereExists($this->baseColorRows($elementId)->selectRaw('1')
            ->whereColumn('color_row.product_id', 'products.id')
            ->whereIn('base_color.slug', $slugs));
    }

    private function baseColorRows(?int $elementId): QueryBuilder
    {
        return DB::table('product_color_rows as color_row')
            ->join('color_solution_base_color as solution_base', 'solution_base.color_solution_id', '=', 'color_row.color_solution_id')
            ->join('base_colors as base_color', 'base_color.id', '=', 'solution_base.base_color_id')
            ->when($elementId === null, fn ($q) => $q->whereNull('color_row.color_element_id'),
                fn ($q) => $q->where('color_row.color_element_id', $elementId));
    }

    /** Базовые цвета, встречающиеся в выборке: по числу товаров, затем по порядку справочника. */
    private function facet(array|Builder $skus, ?int $elementId): array
    {
        return $this->baseColorRows($elementId)
            ->whereIn('color_row.product_id', $skus)
            ->groupBy('base_color.id', 'base_color.name', 'base_color.slug', 'base_color.hex', 'base_color.sort_order')
            ->selectRaw('base_color.id, base_color.name, base_color.slug, base_color.hex, COUNT(DISTINCT color_row.product_id) as product_count')
            ->orderByDesc('product_count')->orderBy('base_color.sort_order')->orderBy('base_color.id')
            ->get()
            ->map(fn ($color) => ['id' => (int) $color->id, 'name' => $color->name, 'slug' => $color->slug,
                'hex' => $color->hex, 'count' => (int) $color->product_count])
            ->all();
    }
}
