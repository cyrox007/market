<?php

namespace Tests\Feature\Api;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Product\Room;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogQuickFixesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function product(Category $category, array $data = []): Product
    {
        $product = Product::factory()->create(array_merge(['price' => 1000, 'parent_product_id' => null], $data));
        $product->taxons()->attach($category);

        return $product;
    }

    public function test_equal_price_pagination_uses_id_tiebreaker_and_rating_is_supported(): void
    {
        $category = Category::factory()->create();
        $first = $this->product($category, ['name' => 'Sort sample 1']);
        $second = $this->product($category, ['name' => 'Sort sample 2']);
        $third = $this->product($category, ['name' => 'Sort sample 3']);
        foreach ([[$first, 2], [$second, 5], [$third, 5]] as [$product, $rating]) {
            $product->reviews()->create(['name' => 'Покупатель', 'rating' => $rating, 'comment' => 'Отзыв', 'is_approved' => true]);
        }
        $this->getJson('/api/v1/products?sort_by=price&sort_order=asc&per_page=1')->assertOk()->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/v1/products?sort_by=price&sort_order=asc&per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', $second->id);
        $this->getJson('/api/v1/products?sort_by=rating&sort_order=desc')->assertOk()
            ->assertJsonPath('data.0.id', $second->id)->assertJsonPath('data.1.id', $third->id);
        $this->getJson('/api/v1/products/search?q=Sort&sort_by=rating&sort_order=desc')->assertOk()->assertJsonPath('data.0.id', $second->id);
    }

    public function test_sale_filter_includes_only_discounted_simple_or_active_variant_products(): void
    {
        $category = Category::factory()->create();
        $simple = $this->product($category, ['original_price' => 1500]);
        $this->product($category, ['original_price' => 1000]);
        $this->product($category, ['original_price' => null]);
        $variable = $this->product($category, ['is_variable' => true]);
        $discountedOffer = Product::factory()->create(['parent_product_id' => $variable->id, 'price' => 100, 'original_price' => 200]);
        $inactiveDiscount = $this->product($category, ['is_variable' => true, 'original_price' => 2000]);
        Product::factory()->create(['parent_product_id' => $inactiveDiscount->id, 'state' => 'inactive', 'price' => 100, 'original_price' => 200]);
        $query = http_build_query(['category_slug' => $category->slug, 'on_sale' => 1, 'sort_by' => 'name']);
        $response = $this->getJson('/api/v1/products?'.$query)->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.filters.has_on_sale', true);
        $this->assertEqualsCanonicalizing([$simple->id, $discountedOffer->id], array_column($response->json('data'), 'id'));
        $this->getJson('/api/v1/products/count?'.$query)->assertOk()->assertExactJson(['total' => 2]);
    }

    public function test_count_does_not_hydrate_products_or_build_facets(): void
    {
        $category = Category::factory()->create();
        $this->product($category);
        DB::enableQueryLog();
        $this->getJson('/api/v1/products/count?category_slug='.$category->slug)->assertOk()->assertExactJson(['total' => 1]);
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        $this->assertStringNotContainsString('product_attribute_values', $queries);
        $this->assertStringNotContainsString('product_filters_meta', $queries);
        $this->assertDoesNotMatchRegularExpression('/(?:^|\n)select\s+\*\s+from\s+["`]?products/i', $queries);
    }

    public function test_multiple_categories_are_union_with_descendants_without_duplicate_products(): void
    {
        $a = Category::factory()->create(['slug' => 'sofas']);
        $child = Category::factory()->create(['parent_id' => $a->id]);
        $b = Category::factory()->create(['slug' => 'beds']);
        $first = $this->product($child);
        $first->taxons()->attach($b);
        $this->product($b);
        $this->product(Category::factory()->create());
        $this->getJson('/api/v1/products?category_slugs=sofas,beds')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonStructure(['meta' => ['filters' => ['price', 'attributes', 'has_on_sale']]]);
        $this->getJson('/api/v1/products/count?category_slugs=sofas,beds')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/v1/products/count?category_slugs=sofas,unknown')->assertUnprocessable()->assertJsonValidationErrors('category_slugs');
    }

    public function test_room_facets_are_present_and_do_not_leak_from_another_room(): void
    {
        $a = Category::factory()->create();
        $b = Category::factory()->create();
        $this->product($a, ['price' => 100]);
        $this->product($b, ['price' => 900]);
        foreach ([['room-a', $a], ['room-b', $b]] as [$slug, $category]) {
            $room = Room::create(['name' => $slug, 'slug' => $slug, 'is_active' => true]);
            $room->productCategories()->attach($category);
        }
        $this->getJson('/api/v1/products?room_slug=room-a')->assertOk()->assertJsonPath('meta.filters.price.min', 100);
        $this->getJson('/api/v1/products?room_slug=room-b')->assertOk()->assertJsonPath('meta.filters.price.min', 900);
        $this->getJson('/api/v1/products/count?room_slug=room-a')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_manufacturer_attribute_accepts_multiple_values_and_exposes_counts(): void
    {
        $category = Category::factory()->create();
        $attribute = Attribute::create(['name' => 'Производитель', 'slug' => 'proizvoditel', 'type' => 'select', 'is_filterable' => true, 'is_use_in_variations' => false]);
        foreach (['factory-a', 'factory-b', 'factory-c'] as $slug) {
            $value = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => $slug, 'slug' => $slug]);
            $this->product($category)->attributeValues()->attach($value, ['attribute_id' => $attribute->id]);
        }
        $query = 'category_slug='.$category->slug.'&attr_proizvoditel=factory-a,factory-b';
        $response = $this->getJson('/api/v1/products?'.$query)->assertOk()->assertJsonPath('meta.total', 2);
        $facet = collect($response->json('meta.filters.attributes'))->firstWhere('slug', 'proizvoditel');
        $this->assertNotNull($facet);
        $this->assertSame([1, 1, 1], array_column($facet['values'], 'count'));
        $this->getJson('/api/v1/products/count?'.$query)->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/v1/products/count?attr_proizvoditel=unknown')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_bad_sort_and_sale_values_are_validation_errors(): void
    {
        $this->getJson('/api/v1/products?sort_by=invalid')->assertUnprocessable();
        $this->getJson('/api/v1/products/count?on_sale=invalid')->assertUnprocessable();
    }

    public function test_review_changes_refresh_rating_order_and_unapproved_reviews_do_not_count(): void
    {
        $category = Category::factory()->create();
        $a = $this->product($category);
        $b = $this->product($category);
        $a->reviews()->create(['name' => 'А', 'rating' => 1, 'comment' => 'Отзыв', 'is_approved' => true]);
        $review = $b->reviews()->create(['name' => 'Б', 'rating' => 5, 'comment' => 'Отзыв', 'is_approved' => false]);
        $this->getJson('/api/v1/products?sort_by=rating&sort_order=desc')->assertOk()->assertJsonPath('data.0.id', $a->id);
        $review->update(['is_approved' => true]);
        $this->getJson('/api/v1/products?sort_by=rating&sort_order=desc')->assertOk()->assertJsonPath('data.0.id', $b->id);
    }

    public function test_filtered_empty_category_does_not_poison_unfiltered_category(): void
    {
        $category = Category::factory()->create();
        $product = $this->product($category);
        $this->getJson('/api/v1/products?category_slug='.$category->slug.'&price_min=999999')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/products?category_slug='.$category->slug)->assertOk()->assertJsonPath('meta.total', 1);
        $product->update(['original_price' => 2000]);
        $this->getJson('/api/v1/products?category_slug='.$category->slug)->assertOk()->assertJsonPath('meta.filters.has_on_sale', true);
    }
}
