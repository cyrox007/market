<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBundleTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_returns_attached_products_in_sort_order(): void
    {
        $owner = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $first = Product::factory()->create(['state' => 'active', 'parent_product_id' => null]);
        $second = Product::factory()->create(['state' => 'active', 'parent_product_id' => null]);
        $unrelated = Product::factory()->create(['state' => 'active', 'parent_product_id' => null]);

        $owner->bundleProducts()->attach($second->id, ['sort_order' => 2]);
        $owner->bundleProducts()->attach($first->id, ['sort_order' => 1]);

        $response = $this->getJson('/api/v1/products/' . $owner->id . '/bundle');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$first->id, $second->id], $ids);
        $this->assertNotContains($unrelated->id, $ids);
    }

    public function test_bundle_returns_404_for_missing_product(): void
    {
        $this->getJson('/api/v1/products/999999/bundle')->assertNotFound();
    }

    public function test_bundle_returns_empty_list_when_no_items(): void
    {
        $owner = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $response = $this->getJson('/api/v1/products/' . $owner->id . '/bundle');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_bundle_cache_invalidated_after_attach(): void
    {
        $owner = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);
        $item = Product::factory()->create(['state' => 'active', 'parent_product_id' => null]);

        $this->getJson('/api/v1/products/' . $owner->id . '/bundle')->assertOk();

        $owner->attachBundleProducts([$item->id]);

        $response = $this->getJson('/api/v1/products/' . $owner->id . '/bundle');
        $response->assertOk();
        $this->assertSame([$item->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_product_show_includes_bundle_and_related_in_single_response(): void
    {
        $owner = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
            'slug' => 'bundle-owner-product',
        ]);
        $bundleItem = Product::factory()->create(['state' => 'active', 'parent_product_id' => null]);
        $owner->bundleProducts()->attach($bundleItem->id, ['sort_order' => 1]);

        $response = $this->getJson('/api/v1/products/' . $owner->slug);

        $response->assertOk()
            ->assertJsonStructure([
                'product' => ['id', 'slug', 'name'],
                'bundle' => ['data'],
                'related' => ['data'],
            ]);

        $this->assertSame(
            [$bundleItem->id],
            collect($response->json('bundle.data'))->pluck('id')->all()
        );
    }
}
