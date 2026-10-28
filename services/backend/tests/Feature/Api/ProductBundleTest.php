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

    public function test_bundle_and_related_for_variant_use_root_product_links(): void
    {
        $owner = Product::factory()->create(['state' => 'active', 'is_variable' => true]);
        $variant = Product::factory()->create(['state' => 'active', 'parent_product_id' => $owner->id]);
        $component = Product::factory()->create(['state' => 'active']);
        $recommendation = Product::factory()->create(['state' => 'active']);
        $owner->attachBundleProducts([$component->id]);
        $owner->attachRelatedProduct($recommendation);

        $this->assertSame(
            $this->getJson('/api/v1/products/'.$owner->id.'/bundle')->assertOk()->json('data'),
            $this->getJson('/api/v1/products/'.$variant->id.'/bundle')->assertOk()->json('data')
        );
        $this->assertSame(
            $this->getJson('/api/v1/products/'.$owner->id.'/related')->assertOk()->json('data'),
            $this->getJson('/api/v1/products/'.$variant->id.'/related')->assertOk()->json('data')
        );
    }

    public function test_bundle_refuses_indirect_cycles(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        $c = Product::factory()->create();

        $this->assertSame([$b->id], $a->attachBundleProducts([$b->id])['attached']);
        $this->assertSame([$c->id], $b->attachBundleProducts([$c->id])['attached']);
        $this->assertSame([], $c->attachBundleProducts([$a->id])['attached']);
        $this->assertDatabaseMissing('product_bundle_products', [
            'product_id' => $c->id,
            'bundle_product_id' => $a->id,
        ]);
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
