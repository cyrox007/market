<?php

namespace Tests\Feature\Api;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Product\Room;
use App\Services\Product\ProductAttributeSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogDimensionsFacetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function attr(string $slug, bool $variation = false): Attribute
    {
        return Attribute::firstOrCreate(['slug' => $slug], ['name' => $slug, 'type' => 'string',
            'is_filterable' => true, 'allow_custom_value' => true, 'is_use_in_variations' => $variation]);
    }

    private function value(Product $product, Attribute $attr, string $value, bool $variant = false): void
    {
        $v = AttributeValue::firstOrCreate(['attribute_id' => $attr->id, 'slug' => $value], ['value' => $value]);
        DB::table($variant ? 'product_variant_attributes' : 'product_product_attributes')->insert([
            'product_id' => $product->id, 'attribute_id' => $attr->id, 'attribute_value_id' => $v->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function root(Category $cat, array $data = []): Product
    {
        $p = Product::factory()->create(array_merge(['parent_product_id' => null, 'price' => 1000], $data));
        $p->taxons()->attach($cat);

        return $p;
    }

    public function test_attribute_discovery_uses_two_membership_queries_instead_of_union_inside_in(): void
    {
        $cat = Category::factory()->create();
        $simple = $this->root($cat);
        $parent = $this->root($cat, ['is_variable' => true]);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id, 'state' => 'active']);
        $this->value($simple, $this->attr('fabric'), 'cotton');
        $this->value($offer, $this->attr('mechanism', true), 'book', true);
        DB::enableQueryLog();
        try {
            $response = $this->getJson('/api/v1/products?category_slug='.$cat->slug)->assertOk();
            $slugs = collect($response->json('meta.filters.attributes'))->pluck('slug')->all();
            $this->assertContains('fabric', $slugs);
            $this->assertContains('mechanism', $slugs);
            $discovery = collect(DB::getQueryLog())->pluck('query')->first(fn ($sql) => str_contains($sql, 'select * from "product_attributes" where ("id" in'));
            $this->assertNotNull($discovery);
            $this->assertStringContainsString('or "id" in', $discovery);
            $this->assertStringNotContainsString('union', strtolower($discovery));
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_dimensions_use_mm_attributes_not_shipping_columns_and_share_count_logic(): void
    {
        $cat = Category::factory()->create();
        $width = $this->attr('sirina-mm');
        $depth = $this->attr('glubina-mm');
        $height = $this->attr('vysota-mm');
        $expected = $this->root($cat, ['width' => 1]);
        foreach ([[$width, '1200'], [$depth, '650'], [$height, '900']] as [$attr, $value]) {
            $this->value($expected, $attr, $value);
        }
        $other = $this->root($cat, ['width' => 1200]);
        $this->value($other, $width, '2000');
        $query = '?category_slug='.$cat->slug.'&width_min=1000&width_max=1500&depth_min=600&height_max=1000';
        $this->getJson('/api/v1/products'.$query)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expected->id)->assertJsonPath('meta.filters.dimensions.width.unit', 'mm');
        $this->getJson('/api/v1/products/count'.$query)->assertOk()->assertExactJson(['total' => 1]);
    }

    public function test_color_size_price_sale_and_dimensions_cannot_match_different_siblings(): void
    {
        $cat = Category::factory()->create();
        $color = $this->attr('color', true);
        $size = $this->attr('size', true);
        $width = $this->attr('sirina-mm');
        $p = $this->root($cat, ['is_variable' => true]);
        $red = Product::factory()->create(['parent_product_id' => $p->id, 'price' => 100, 'original_price' => 100]);
        $blue = Product::factory()->create(['parent_product_id' => $p->id, 'price' => 200, 'original_price' => 300]);
        $this->value($red, $color, 'red', true);
        $this->value($red, $size, 'small', true);
        $this->value($red, $width, '1000');
        $this->value($blue, $color, 'blue', true);
        $this->value($blue, $size, 'large', true);
        $this->value($blue, $width, '2000');
        foreach (['colors=red&sizes=large', 'colors=red&price_min=150', 'colors=red&on_sale=1', 'colors=red&width_min=1500'] as $query) {
            $this->getJson('/api/v1/products/count?'.$query)->assertOk()->assertExactJson(['total' => 0]);
        }
        $this->getJson('/api/v1/products/count?colors=blue&sizes=large&price_min=150&on_sale=1&width_min=1500')
            ->assertOk()->assertExactJson(['total' => 1]);
        $blue->update(['state' => 'inactive']);
        $this->getJson('/api/v1/products/count?colors=blue')->assertExactJson(['total' => 0]);
    }

    public function test_facets_exclude_own_selection_but_respect_other_filters_on_same_sku(): void
    {
        $cat = Category::factory()->create();
        $color = $this->attr('color', true);
        $size = $this->attr('size', true);
        $p = $this->root($cat, ['is_variable' => true]);
        foreach ([['red', 'small'], ['blue', 'large'], ['red', 'small']] as [$c, $s]) {
            $v = Product::factory()->create(['parent_product_id' => $p->id]);
            $this->value($v, $color, $c, true);
            $this->value($v, $size, $s, true);
        }
        $response = $this->getJson('/api/v1/products?category_slug='.$cat->slug.'&colors=blue&sizes=small')->assertOk();
        $response->assertJsonCount(0, 'data')->assertJsonPath('meta.filters.facet_mode', 'excluding_self');
        $this->assertSame(['red'], array_column($response->json('meta.filters.colors'), 'slug'));
        $this->assertSame(2, $response->json('meta.filters.colors.0.count')); // Two independent catalog positions.
        $this->assertSame(['large'], array_column($response->json('meta.filters.sizes'), 'slug'));
    }

    public function test_numeric_custom_values_and_inheritance_reject_text_and_override_parent(): void
    {
        $cat = Category::factory()->create();
        $width = $this->attr('sirina-mm');
        $p = $this->root($cat, ['is_variable' => true]);
        $this->value($p, $width, '1200');
        $v = Product::factory()->create(['parent_product_id' => $p->id]);
        $this->getJson('/api/v1/products/count?width_min=1100&width_max=1300')->assertExactJson(['total' => 1]);
        DB::table('product_product_attributes')->insert(['product_id' => $v->id, 'attribute_id' => $width->id,
            'custom_value' => '2000,5', 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/api/v1/products/count?width_max=1300')->assertExactJson(['total' => 0]);
        $this->getJson('/api/v1/products/count?width_min=2000&width_max=2001')->assertExactJson(['total' => 1]);
        foreach (['1200 мм', 'abc', '0', '-1200', '1.2.3'] as $bad) {
            $product = $this->root($cat);
            $this->value($product, $width, $bad);
        }
        $response = $this->getJson('/api/v1/products?category_slug='.$cat->slug)->assertOk();
        $response->assertJsonPath('meta.filters.dimensions.width.min', 2000.5);
    }

    public function test_dimension_validation_and_cache_isolation(): void
    {
        foreach (['width_min=-1', 'depth_max=abc', 'height_min=20&height_max=10'] as $invalid) {
            $this->getJson('/api/v1/products/count?'.$invalid)->assertUnprocessable();
        }
        $cat = Category::factory()->create();
        $width = $this->attr('sirina-mm');
        $p = $this->root($cat);
        $this->value($p, $width, '1200');
        $this->getJson('/api/v1/products?width_min=1000')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products?width_min=2000')->assertJsonCount(0, 'data');
        $revision = Cache::get('catalog_filters_revision');
        app(ProductAttributeSyncService::class)->sync($p, [['attribute_id' => $width->id, 'custom_value' => '2500']]);
        $this->assertNotSame($revision, Cache::get('catalog_filters_revision'));
        $this->getJson('/api/v1/products?width_min=2000')->assertJsonCount(1, 'data');
    }

    public function test_room_mandatory_color_is_not_removed_by_facet_self_exclusion(): void
    {
        $cat = Category::factory()->create();
        $room = Room::create(['name' => 'Room', 'slug' => 'restricted-room', 'filters' => ['colors' => ['red']]]);
        $room->productCategories()->attach($cat);
        $color = $this->attr('color', true);
        foreach (['red', 'blue'] as $c) {
            $this->value($this->root($cat), $color, $c);
        }
        $response = $this->getJson('/api/v1/products?room_slug='.$room->slug.'&colors=blue')->assertOk();
        $response->assertJsonCount(0, 'data');
        $this->assertSame(['red'], array_column($response->json('meta.filters.colors'), 'slug'));
    }

    public function test_room_attribute_color_cannot_be_bypassed_by_colors_alias(): void
    {
        $cat = Category::factory()->create();
        $room = Room::create(['name' => 'Room alias', 'slug' => 'room-alias',
            'filters' => ['attributes' => ['color' => ['red']]]]);
        $room->productCategories()->attach($cat);
        $color = $this->attr('color', true);
        foreach (['red', 'blue'] as $c) {
            $this->value($this->root($cat), $color, $c);
        }
        $this->getJson('/api/v1/products/count?room_slug='.$room->slug.'&colors=blue')->assertExactJson(['total' => 0]);
        $this->getJson('/api/v1/products/count?room_slug='.$room->slug.'&colors=red')->assertExactJson(['total' => 1]);
    }

    public function test_dimension_and_price_ranges_exclude_own_bounds_and_sale_uses_same_sku(): void
    {
        $cat = Category::factory()->create();
        $width = $this->attr('sirina-mm');
        $color = $this->attr('color', true);
        foreach ([[1000, 100, 'red', 100], [2000, 200, 'red', 300], [3000, 300, 'blue', 400]] as [$w, $price, $c, $old]) {
            $p = $this->root($cat, ['price' => $price, 'original_price' => $old]);
            $this->value($p, $width, (string) $w);
            $this->value($p, $color, $c);
        }
        $r = $this->getJson('/api/v1/products?category_slug='.$cat->slug.'&colors=red&width_max=1500&price_max=150')->assertOk();
        $this->assertEquals(1000, $r->json('meta.filters.dimensions.width.max')); // Price still applies.
        $this->assertFalse($r->json('meta.filters.has_on_sale'));
        $r = $this->getJson('/api/v1/products?category_slug='.$cat->slug.'&colors=red&width_max=1500')->assertOk();
        $this->assertEquals(2000, $r->json('meta.filters.dimensions.width.max'));
        $this->assertEquals(100, $r->json('meta.filters.price.max')); // Width still applies.
    }

    public function test_regular_attributes_cannot_mix_siblings_and_own_value_overrides_parent(): void
    {
        $cat = Category::factory()->create();
        $maker = $this->attr('proizvoditel');
        $color = $this->attr('color', true);
        $p = $this->root($cat, ['is_variable' => true]);
        $this->value($p, $maker, 'factory-a');
        $red = Product::factory()->create(['parent_product_id' => $p->id]);
        $blue = Product::factory()->create(['parent_product_id' => $p->id]);
        $this->value($red, $color, 'red', true);
        $this->value($blue, $color, 'blue', true);
        $this->value($blue, $maker, 'factory-b');
        $this->getJson('/api/v1/products/count?colors=red&attr_proizvoditel=factory-b')->assertExactJson(['total' => 0]);
        $this->getJson('/api/v1/products/count?colors=blue&attr_proizvoditel=factory-a')->assertExactJson(['total' => 0]);
        $this->getJson('/api/v1/products/count?colors=red&attr_proizvoditel=factory-a')->assertExactJson(['total' => 1]);
    }
}
