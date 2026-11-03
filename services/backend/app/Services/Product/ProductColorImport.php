<?php

namespace App\Services\Product;

use App\Models\Product\Color\ColorSlug;
use App\Models\Product\Color\ColorSolution;
use Illuminate\Support\Facades\DB;

/**
 * Цвет из внешних источников → основной цвет товара (цветовые решения).
 * Решение ищется по названию без учёта рода; базовые цвета ставятся решению, только пока их нет.
 */
class ProductColorImport
{
    /** @var array<string, int>|null ключ названия → id решения */
    private ?array $solutions = null;

    /** @var array<string, int>|null ключ названия → id базового цвета */
    private ?array $baseColors = null;

    /** Поле источника — цвет товара. */
    public static function isColorField(string $name): bool
    {
        return in_array(mb_strtolower(trim($name)), ['цвет', 'color'], true);
    }

    /**
     * Заменить основной цвет товара решением с этим названием.
     *
     * @param  string[]  $baseColorNames  базовые цвета решения, пока у него их нет; пусто — по названию
     * @param  bool  $onlyIfEmpty  не трогать товар, у которого основной цвет уже задан
     */
    public function setMainColor(int $productId, string $name, array $baseColorNames = [], bool $onlyIfEmpty = false): void
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($name === '') {
            return;
        }
        $rowId = DB::table('product_color_rows')->where('product_id', $productId)->whereNull('color_element_id')->value('id');
        if ($rowId !== null && $onlyIfEmpty) {
            return;
        }
        $solutionId = $this->solution($name, $baseColorNames);
        if ($rowId !== null) {
            DB::table('product_color_rows')->where('id', $rowId)->update(['color_solution_id' => $solutionId, 'updated_at' => now()]);

            return;
        }
        DB::table('product_color_rows')->insert([
            'product_id' => $productId,
            'color_element_id' => null,
            'color_solution_id' => $solutionId,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Название составного цвета из нескольких значений источника.
     *
     * @param  string[]  $values
     */
    public static function compositeName(array $values): string
    {
        $values = array_values(array_unique(array_filter(array_map(fn ($value) => trim((string) $value), $values), fn ($value) => $value !== '')));

        return implode(' / ', array_map(fn (string $value) => mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1), $values));
    }

    /** Ключ сравнения названий: без учёта регистра, «ё», дефисов и родовых окончаний. */
    public static function key(string $name): string
    {
        $words = preg_split('/[\s\-]+/u', str_replace('ё', 'е', mb_strtolower(trim($name))), -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', array_map(fn (string $word) => preg_replace('/(ый|ий|ой|ая|яя|ое|ее|ые|ие)$/u', '', $word), $words));
    }

    /**
     * Найти или создать решение по названию.
     *
     * @param  string[]  $baseColorNames
     */
    public function solution(string $name, array $baseColorNames = []): int
    {
        $this->solutions ??= DB::table('color_solutions')->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $existing) => [static::key($existing) => (int) $id])->all();
        $key = static::key($name);
        if (isset($this->solutions[$key])) {
            $this->fillBaseColors($this->solutions[$key], $baseColorNames);

            return $this->solutions[$key];
        }

        $solution = new ColorSolution(['name' => $name]);
        $solution->slug = ColorSlug::unique(ColorSolution::class, $solution, $name);
        $solution->saveQuietly();
        $this->solutions[$key] = (int) $solution->id;
        $this->fillBaseColors($solution->id, $baseColorNames === [] ? [$name] : $baseColorNames);

        return $this->solutions[$key];
    }

    /** Базовые цвета ставятся, только если у решения их ещё нет. */
    private function fillBaseColors(int $solutionId, array $names): void
    {
        if ($names === [] || DB::table('color_solution_base_color')->where('color_solution_id', $solutionId)->exists()) {
            return;
        }
        $this->baseColors ??= DB::table('base_colors')->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [static::key($name) => (int) $id])->all();

        $ids = [];
        foreach ($names as $name) {
            // Однотонное: базовый цвет, возможно с оттенком или фактурой.
            $plain = trim(preg_replace('/(^|\s)(темно|светло|глянец|глянцев|матов|мат)(?=\s|$)/u', ' ', static::key($name)));
            if (isset($this->baseColors[$plain])) {
                $ids[$this->baseColors[$plain]] = true;
            }
        }
        foreach (array_keys($ids) as $baseColorId) {
            DB::table('color_solution_base_color')->insert(['color_solution_id' => $solutionId, 'base_color_id' => $baseColorId]);
        }
    }
}
