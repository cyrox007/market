<?php

namespace Tests\Feature\Api;

use App\Models\Product\Category;
use App\Models\Product\Product;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ProductColorFixtures;
use Tests\TestCase;

class ProductColorsApiTest extends TestCase
{
    use ProductColorFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** @return array{0: Product, 1: Product, 2: Product, 3: Product} */
    private function sofa(): array
    {
        $parent = Product::factory()->create(['slug' => 'sofa-colors', 'is_variable' => true, 'state' => 'active']);
        $variants = [];
        foreach ([['white', 'oak-white'], ['white', 'black'], ['gray', 'black']] as [$main, $facade]) {
            $variant = Product::factory()->create(['parent_product_id' => $parent->id, 'state' => 'active']);
            $this->colorRow($variant, $main);
            $this->colorRow($variant, $facade, 'fasad', $facade === 'oak-white' ? ['oak', 'white'] : []);
            $this->colorRow($variant, 'oak', 'korpus');
            $variants[] = $variant;
        }

        return [$parent, ...$variants];
    }

    public function test_card_lists_palette_and_color_options_by_differing_elements(): void
    {
        [$parent, $a, $b, $c] = $this->sofa();

        $product = $this->getJson('/api/v1/products/sofa-colors')->assertOk()->json('product');

        $groups = collect($product['color_options'])->keyBy('key');
        $this->assertSame(['main', 'fasad'], $groups->keys()->all()); // Корпус одинаков у всех вариаций.
        $this->assertSame(['white', 'gray'], array_column($groups['main']['options'], 'key'));
        $this->assertSame([$a->id, $b->id], $groups['main']['options'][0]['variant_ids']);
        $this->assertSame(['oak-white', 'black'], array_column($groups['fasad']['options'], 'key'));
        $this->assertEqualsCanonicalizing(['oak', 'white'], array_column($groups['fasad']['options'][0]['solution']['base_colors'], 'slug'));
        $this->assertSame('fasad', $groups['fasad']['element']['slug']);

        $this->assertSame([], $product['colors']);
        $palette = collect($product['palette'])->keyBy(fn ($row) => $row['element']['slug'] ?? 'main');
        $this->assertSame(['white', 'gray'], array_column($palette['main']['solutions'], 'slug'));
        $this->assertSame(['oak-white', 'black'], array_column($palette['fasad']['solutions'], 'slug'));
        $this->assertSame(['oak'], array_column($palette['korpus']['solutions'], 'slug'));
        $this->assertArrayHasKey('icon_url', $palette['main']['solutions'][0]);
        $this->assertSame(['main' => 'white', 'fasad' => 'black', 'korpus' => 'oak'],
            $this->keys(collect($product['variants'])->firstWhere('id', $b->id)['colors']));
        $this->assertNull($product['selected_color_options']);
    }

    public function test_variant_is_selected_by_color_of_each_element(): void
    {
        [, $a, $b, $c] = $this->sofa();

        $response = $this->getJson('/api/v1/products/sofa-colors?color_options[main]=white&color_options[fasad]=black')->assertOk();
        $response->assertJsonPath('product.id', $b->id);
        $this->assertSame(['fasad' => 'black', 'korpus' => 'oak', 'main' => 'white'], collect($response->json('product.selected_color_options'))->sortKeys()->all());

        $this->getJson('/api/v1/products/sofa-colors?color_options[fasad]=oak-white')->assertOk()->assertJsonPath('product.id', $a->id);
        $this->getJson('/api/v1/products/sofa-colors?color_options[main]=gray&color_options[fasad]=oak-white')
            ->assertOk()->assertJsonPath('product.selected_color_options', null);
    }

    public function test_catalog_returns_each_sku_colors_and_complete_sibling_palette_across_pages(): void
    {
        [$parent, $whiteOak, $whiteBlack, $grayBlack] = $this->sofa();
        $category = Category::factory()->create();
        $parent->taxons()->attach($category);
        $inactive = Product::factory()->create(['parent_product_id' => $parent->id, 'state' => 'inactive']);
        $this->colorRow($inactive, 'pink');

        $expected = [
            $whiteOak->id => ['main' => 'white', 'fasad' => 'oak-white', 'korpus' => 'oak'],
            $whiteBlack->id => ['main' => 'white', 'fasad' => 'black', 'korpus' => 'oak'],
            $grayBlack->id => ['main' => 'gray', 'fasad' => 'black', 'korpus' => 'oak'],
        ];
        $seen = [];
        for ($page = 1; $page <= 3; $page++) {
            $response = $this->getJson('/api/v1/products?category_slug='.$category->slug.'&per_page=1&page='.$page)
                ->assertOk()->assertJsonPath('meta.total', 3);
            $sku = $response->json('data.0');
            $seen[] = $sku['id'];
            $this->assertSame($parent->id, $sku['parent_id']);
            $this->assertSame($expected[$sku['id']], $this->keys($sku['colors']));

            $palette = collect($sku['palette'])->keyBy(fn ($row) => $row['element']['slug'] ?? 'main');
            $this->assertSame(['white', 'gray'], array_column($palette['main']['solutions'], 'slug'));
            $this->assertSame(['oak-white', 'black'], array_column($palette['fasad']['solutions'], 'slug'));
            $this->assertSame(['oak'], array_column($palette['korpus']['solutions'], 'slug'));
            $this->assertEqualsCanonicalizing(['oak', 'white'], array_column($palette['fasad']['solutions'][0]['base_colors'], 'slug'));
            $this->assertArrayHasKey('icon_url', $palette['fasad']['solutions'][0]);
        }
        $this->assertEqualsCanonicalizing(array_keys($expected), $seen);
    }

    /** @return array<string, string> элемент → slug решения */
    private function keys(array $rows): array
    {
        return collect($rows)->mapWithKeys(fn ($row) => [$row['element']['slug'] ?? 'main' => $row['solution']['slug']])->all();
    }
}
