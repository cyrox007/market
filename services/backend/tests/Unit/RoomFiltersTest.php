<?php

namespace Tests\Unit;

use App\Models\Product\RoomFilters;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тесты сужения фильтров комнаты — без БД.
 */
class RoomFiltersTest extends TestCase
{
    public function test_price_min_takes_the_stricter_higher_bound(): void
    {
        $r = RoomFilters::narrow(['price_min' => 20000], ['price_min' => 35000]);
        $this->assertSame(35000.0, $r['price_min']);
    }

    public function test_price_min_child_cannot_relax_parent(): void
    {
        // Потомок задаёт меньший порог — остаётся родительский (строже).
        $r = RoomFilters::narrow(['price_min' => 35000], ['price_min' => 10000]);
        $this->assertSame(35000.0, $r['price_min']);
    }

    public function test_price_max_takes_the_stricter_lower_bound(): void
    {
        $r = RoomFilters::narrow(['price_max' => 100000], ['price_max' => 60000]);
        $this->assertSame(60000.0, $r['price_max']);
    }

    public function test_colors_intersect(): void
    {
        $r = RoomFilters::narrow(['colors' => ['seryi', 'temno-sinii', 'bezevyi']], ['colors' => ['seryi', 'temno-sinii']]);
        $this->assertSame(['seryi', 'temno-sinii'], $r['colors']);
    }

    public function test_attributes_intersect_per_slug(): void
    {
        $r = RoomFilters::narrow(
            ['attributes' => ['material' => ['tkan', 'kozha'], 'size' => ['xl']]],
            ['attributes' => ['material' => ['tkan']]]
        );
        $this->assertSame(['tkan'], $r['attributes']['material']);
        // Характеристику, которой нет у потомка, родитель сохраняет.
        $this->assertSame(['xl'], $r['attributes']['size']);
    }

    public function test_empty_add_keeps_base_untouched(): void
    {
        $base = ['price_min' => 20000, 'colors' => ['seryi']];
        $this->assertSame($base, RoomFilters::narrow($base, []));
    }

    public function test_resolve_chain_applies_root_to_leaf(): void
    {
        $r = RoomFilters::resolveChain([
            ['price_min' => 20000],
            ['price_min' => 35000],
            ['price_min' => 50000],
        ]);
        $this->assertSame(50000.0, $r['price_min']);
    }

    public function test_resolve_chain_intersects_colors_down_the_tree(): void
    {
        $r = RoomFilters::resolveChain([
            ['colors' => ['seryi', 'temno-sinii', 'bezevyi']],
            ['colors' => ['seryi', 'temno-sinii']],
            ['colors' => ['seryi']],
        ]);
        $this->assertSame(['seryi'], $r['colors']);
    }
}
