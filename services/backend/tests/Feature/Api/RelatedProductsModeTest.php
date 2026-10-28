<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedProductsModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_mode_empty_does_not_trigger_automatic_fallback(): void
    {
        $root = Product::factory()->create(['state' => 'active', 'related_products_mode' => 'manual']);
        Product::factory()->create(['state' => 'active']);

        $this->getJson('/api/v1/products/'.$root->id.'/related')
            ->assertOk()->assertJsonPath('data', []);
    }

    public function test_off_mode_hides_even_explicit_related_links(): void
    {
        $root = Product::factory()->create(['state' => 'active', 'related_products_mode' => 'off']);
        $other = Product::factory()->create(['state' => 'active']);
        $root->attachRelatedProduct($other);

        $this->getJson('/api/v1/products/'.$root->id.'/related')
            ->assertOk()->assertJsonPath('data', []);
    }

    public function test_automatic_mode_excludes_kit_components(): void
    {
        $root = Product::factory()->create(['state' => 'active', 'related_products_mode' => 'automatic']);
        $component = Product::factory()->create(['state' => 'active']);
        $suggested = Product::factory()->create(['state' => 'active']);
        $root->attachBundleProducts([$component->id]);

        $ids = collect($this->getJson('/api/v1/products/'.$root->id.'/related')->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertContains($suggested->id, $ids);
        $this->assertNotContains($component->id, $ids);
    }

    public function test_manual_mode_preserves_order_from_admin_pivot(): void
    {
        $root = Product::factory()->create(['state' => 'active', 'related_products_mode' => 'manual']);
        $first = Product::factory()->create(['state' => 'active']);
        $second = Product::factory()->create(['state' => 'active']);
        $root->attachRelatedProduct($first);
        $root->attachRelatedProduct($second);
        $root->relatedProducts()->updateExistingPivot($first->id, ['sort_order' => 2]);
        $root->relatedProducts()->updateExistingPivot($second->id, ['sort_order' => 1]);
        Product::flushAllProductCaches();

        $ids = collect($this->getJson('/api/v1/products/'.$root->id.'/related')->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertSame([$second->id, $first->id], $ids);
    }
}
