<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use Tests\Concerns\ProductColorFixtures;
use Tests\TestCase;

class DirectCheckoutTest extends TestCase
{
    use ProductColorFixtures;

    public function test_one_click_prepares_checkout_without_lead_or_order(): void
    {
        $sku = Product::factory()->create(['price' => 1000]);
        $this->postJson('/api/v1/orders/one-click', ['product_id' => $sku->id, 'quantity' => 2])
            ->assertOk()->assertJsonPath('mode', 'direct_checkout')->assertJsonPath('items.0.product_id', $sku->id)
            ->assertJsonPath('subtotal', 2000);
        $this->assertDatabaseCount('one_click_leads', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_one_click_exposes_selected_sku_colors_without_changing_submit_items(): void
    {
        $sku = Product::factory()->create(['price' => 1000]);
        $this->colorRow($sku, 'oak-white', 'fasad', ['oak', 'white']);
        $response = $this->postJson('/api/v1/orders/one-click', ['product_id' => $sku->id, 'quantity' => 2])
            ->assertOk()->assertJsonPath('colors.0.element.slug', 'fasad')
            ->assertJsonPath('colors.0.solution.slug', 'oak-white')
            ->assertJsonPath('colors.0.solution.icon_url', null);
        $this->assertSame(['oak', 'white'], array_column($response->json('colors.0.solution.base_colors'), 'slug'));
        $this->assertSame([['product_id' => $sku->id, 'quantity' => 2]], $response->json('items'));
    }

    public function test_foreign_variant_and_invalid_quantity_are_rejected(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        $this->postJson('/api/v1/orders/one-click', ['product_id' => $a->id, 'variant_id' => $b->id, 'quantity' => 1])->assertUnprocessable();
        $this->postJson('/api/v1/orders/one-click', ['product_id' => $a->id, 'quantity' => 0])->assertUnprocessable();
    }
}
