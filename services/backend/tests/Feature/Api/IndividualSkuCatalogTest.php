<?php

namespace Tests\Feature\Api;

use App\Models\Product\Attribute;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use App\Services\Inventory\WarehouseStockResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndividualSkuCatalogTest extends TestCase
{
    private function items(): array
    {
        WarehouseStockResolver::clearCache();
        $category = Category::factory()->create();
        $parent = Product::factory()->create(['name' => 'Бруно', 'is_variable' => true, 'state' => 'active']);
        $parent->taxons()->attach($category);
        $red = Product::factory()->create(['name' => 'Красный', 'sku' => 'AUDIT-RED-001', 'parent_product_id' => $parent->id, 'state' => 'active', 'price' => 100]);
        $blue = Product::factory()->create(['name' => 'Синий', 'sku' => 'AUDIT-BLUE-002', 'parent_product_id' => $parent->id, 'state' => 'active', 'price' => 300]);
        $simple = Product::factory()->create(['state' => 'active', 'price' => 200]);
        $simple->taxons()->attach($category);
        $color = Attribute::create(['name' => 'Цвет', 'slug' => 'color', 'type' => 'color', 'is_use_in_variations' => true, 'is_filterable' => true]);
        foreach ([[$red, 'red'], [$blue, 'blue']] as [$product, $name]) {
            $value = $color->values()->create(['value' => $name, 'slug' => $name]);
            DB::table('product_variant_attributes')->insert(['product_id' => $product->id, 'attribute_id' => $color->id, 'attribute_value_id' => $value->id]);
        }

        return [$category, $parent, $red, $blue, $simple];
    }

    public function test_catalog_pagination_counts_each_sku_once_with_own_price_and_url(): void
    {
        [$category, $parent, $red, $blue, $simple] = $this->items();
        $url = '/api/v1/products?category_slug='.$category->slug.'&sort_by=price&sort_order=asc&per_page=2';
        $first = $this->getJson($url)->assertOk()->assertJsonPath('meta.total', 3);
        $this->assertSame([$red->id, $simple->id], array_column($first->json('data'), 'id'));
        $first->assertJsonPath('data.0.slug', $red->slug)->assertJsonPath('data.0.sku', $red->sku)->assertJsonPath('data.0.parent_id', $parent->id);
        $this->assertSame(100.0, (float) $first->json('data.0.price'));
        $this->getJson($url.'&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $blue->id);
        $this->getJson('/api/v1/products/count?category_slug='.$category->slug)->assertExactJson(['total' => 3]);
    }

    public function test_search_and_filters_apply_to_the_same_offer(): void
    {
        [$category, $parent, $red, $blue] = $this->items();
        $this->getJson('/api/v1/products/search?q=AUDIT-BLUE-002')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $blue->id);
        $this->getJson('/api/v1/products/search?q='.urlencode('Бруно').'&colors=red')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $red->id);
        $this->getJson('/api/v1/products/search?q=AUDIT-RED-001&price_min=150')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/products?colors=red&price_min=150')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_facets_count_sibling_offers_individually(): void
    {
        [$category, $parent, $red, $blue] = $this->items();
        $color = Attribute::where('slug', 'color')->firstOrFail();
        $redValue = $color->values()->where('slug', 'red')->firstOrFail();
        DB::table('product_variant_attributes')->where('product_id', $blue->id)->update(['attribute_value_id' => $redValue->id]);
        $response = $this->getJson('/api/v1/products?category_slug='.$category->slug.'&colors=red')->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(2, $response->json('meta.filters.colors.0.count'));
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_inactive_parent_hides_only_its_offers_and_empty_group_is_not_sellable(): void
    {
        [$category, $parent, $red, $blue, $simple] = $this->items();
        $parent->update(['state' => 'inactive']);
        Product::factory()->create(['state' => 'active', 'is_variable' => true]);
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $simple->id);
    }

    public function test_regional_offer_price_and_visibility_do_not_leak_to_siblings(): void
    {
        [$category, $parent, $red, $blue, $simple] = $this->items();
        $location = ShippingLocation::factory()->create(['is_active' => true]);
        DB::table('product_region_rules')->insert([
            ['product_id' => $parent->id, 'variant_id' => $red->id, 'shipping_location_id' => $location->id, 'price_override' => 900, 'is_hidden' => false, 'is_active' => true, 'priority' => 100],
            ['product_id' => $parent->id, 'variant_id' => $blue->id, 'shipping_location_id' => $location->id, 'price_override' => null, 'is_hidden' => true, 'is_active' => true, 'priority' => 100],
        ]);
        $query = '?shipping_location_id='.$location->id.'&sort_by=price&sort_order=asc';
        $response = $this->getJson('/api/v1/products'.$query)->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([$simple->id, $red->id], array_column($response->json('data'), 'id'));
        $this->assertSame(900.0, (float) $response->json('data.1.price'));
        $this->getJson('/api/v1/products'.$query.'&price_min=800')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $red->id);
        $this->getJson('/api/v1/products/count'.$query.'&price_min=800')->assertExactJson(['total' => 1]);
    }

    public function test_specific_visible_offer_overrides_hidden_parent_rule(): void
    {
        [$category, $parent, $red, $blue, $simple] = $this->items();
        $location = ShippingLocation::factory()->create(['is_active' => true]);
        DB::table('product_region_rules')->insert([
            ['product_id' => $parent->id, 'variant_id' => null, 'shipping_location_id' => $location->id, 'is_hidden' => true, 'is_active' => true, 'priority' => 100],
            ['product_id' => $parent->id, 'variant_id' => $red->id, 'shipping_location_id' => $location->id, 'is_hidden' => false, 'is_active' => true, 'priority' => 100],
        ]);
        $response = $this->getJson('/api/v1/products?shipping_location_id='.$location->id)->assertOk();
        $this->assertEqualsCanonicalizing([$red->id, $simple->id], array_column($response->json('data'), 'id'));
    }

    public function test_home_lists_and_existing_manual_collections_return_skus_not_groups(): void
    {
        [$category, $parent, $red, $blue, $simple] = $this->items();
        $this->getJson('/api/v1/products/new')->assertOk()->assertJsonCount(3, 'data');
        $collection = \App\Models\Product\ProductCollection::create(['name' => 'Audit', 'slug' => 'audit', 'is_active' => true, 'is_auto' => false, 'limit' => 10]);
        $collection->products()->attach($parent->id, ['sort_order' => 1]);
        $collection->products()->attach($simple->id, ['sort_order' => 0]);
        $response = $this->getJson('/api/v1/products/collections/audit')->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame([$simple->id, $red->id, $blue->id], array_column($response->json('data'), 'id'));
    }
}
