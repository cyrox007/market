<?php

namespace Tests\Feature\Catalog;

use App\Models\Product\Color\ColorSolution;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyColorTransferTest extends TestCase
{
    private int $attributeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attributeId = DB::table('product_attributes')->insertGetId(['name' => 'Цвет', 'slug' => 'color', 'type' => 'color']);
    }

    private function color(Product $product, ?string $value, ?string $custom = null): void
    {
        $valueId = $value === null ? null : (DB::table('product_attribute_values')->where('value', $value)->value('id')
            ?? DB::table('product_attribute_values')->insertGetId(['attribute_id' => $this->attributeId, 'value' => $value, 'slug' => md5($value)]));
        DB::table('product_product_attributes')->insert([
            'product_id' => $product->id,
            'attribute_id' => $this->attributeId,
            'attribute_value_id' => $valueId,
            'custom_value' => $custom,
        ]);
    }

    private function transfer(): void
    {
        (require database_path('migrations/2026_11_03_120000_create_color_solutions.php'))->up();
    }

    private function mainColor(Product $product): ?string
    {
        return $product->colorRows()->whereNull('color_element_id')->first()?->solution->name;
    }

    public function test_values_become_main_colors_and_gender_duplicates_merge(): void
    {
        $white = Product::factory()->create();
        $whiteFem = Product::factory()->create();
        $two = Product::factory()->create();
        $custom = Product::factory()->create();
        $this->color($white, 'Белый');
        $this->color($whiteFem, 'Белая');
        $this->color($two, 'Темно-синяя');
        $this->color($two, 'Натуральное дерево');
        $this->color($custom, null, 'Дуб Сонома');

        $this->transfer();
        $this->transfer();

        $this->assertSame('Белый', $this->mainColor($white));
        $this->assertSame('Белый', $this->mainColor($whiteFem));
        // Несколько значений — один составной цвет с базовыми цветами каждого.
        $this->assertSame('Темно-синяя / Натуральное дерево', $this->mainColor($two));
        $this->assertSame(['Синий'], ColorSolution::where('name', 'Темно-синяя / Натуральное дерево')->sole()->baseColors->pluck('name')->all());
        $this->assertSame('Дуб Сонома', $this->mainColor($custom));
        $this->assertSame(3, ColorSolution::count());
        $this->assertSame(1, $white->colorRows()->count());
    }

    public function test_base_color_is_set_only_for_plain_names(): void
    {
        foreach (['Черный', 'Белый глянец', 'Светло-серая', 'Натуральное дерево'] as $name) {
            $this->color(Product::factory()->create(), $name);
        }

        $this->transfer();

        $base = ColorSolution::with('baseColors')->get()->mapWithKeys(fn ($s) => [$s->name => $s->baseColors->pluck('name')->all()]);
        $this->assertSame(['Чёрный'], $base['Черный']);
        $this->assertSame(['Белый'], $base['Белый глянец']);
        $this->assertSame(['Серый'], $base['Светло-серая']);
        $this->assertSame([], $base['Натуральное дерево']);
    }

    public function test_parent_color_goes_to_variants_without_own_color(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        $plain = Product::factory()->create(['parent_product_id' => $parent->id]);
        $own = Product::factory()->create(['parent_product_id' => $parent->id]);
        $this->color($parent, 'Белый');
        $this->color($own, 'Черный');

        $this->transfer();

        $this->assertSame('Белый', $this->mainColor($plain));
        $this->assertSame('Черный', $this->mainColor($own));
        $this->assertSame(0, $parent->colorRows()->count());
    }
}
