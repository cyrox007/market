<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use App\Models\Product\ProductCollection;
use Tests\TestCase;

class ManualFeaturedCollectionTest extends TestCase
{
    public function test_manual_popular_products_do_not_require_or_fabricate_sales(): void
    {
        $first = Product::factory()->create(['units_sold' => 0, 'is_variable' => false, 'parent_product_id' => null, 'state' => 'active']);
        $second = Product::factory()->create(['units_sold' => 0, 'is_variable' => false, 'parent_product_id' => null, 'state' => 'active']);
        $collection = ProductCollection::create(['name' => 'Популярные товары', 'slug' => 'featured', 'scope_type' => 'featured', 'is_auto' => false, 'is_active' => true, 'limit' => 12]);
        $collection->products()->attach([$first->id => ['sort_order' => 2], $second->id => ['sort_order' => 1]]);
        $this->getJson('/api/v1/products/featured')->assertOk()->assertJsonPath('data.0.id', $second->id)->assertJsonPath('data.1.id', $first->id);
        $this->assertSame(0, (int) $first->fresh()->units_sold);
    }
}
