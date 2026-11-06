<?php

namespace Tests\Concerns;

use App\Models\Product\Color\BaseColor;
use App\Models\Product\Color\ColorElement;
use App\Models\Product\Color\ColorSolution;
use App\Models\Product\Product;

/** Цвета товаров в тестах: slug решения по умолчанию = slug его единственного базового цвета. */
trait ProductColorFixtures
{
    /** @param  string[]  $baseColors  slug базовых цветов; пусто — один, одноимённый решению */
    protected function colorSolution(string $slug, array $baseColors = []): ColorSolution
    {
        $solution = ColorSolution::firstOrCreate(['slug' => $slug], ['name' => $slug]);
        foreach ($baseColors ?: [$slug] as $base) {
            $solution->baseColors()->syncWithoutDetaching([BaseColor::firstOrCreate(['slug' => $base], ['name' => $base])->id]);
        }

        return $solution;
    }

    /** Строка цвета товара: $element null — основной цвет. */
    protected function colorRow(Product $product, string $solution, ?string $element = null, array $baseColors = []): void
    {
        $product->colorRows()->create([
            'color_element_id' => $element === null ? null : ColorElement::firstOrCreate(['slug' => $element], ['name' => $element])->id,
            'color_solution_id' => $this->colorSolution($solution, $baseColors)->id,
        ]);
    }
}
