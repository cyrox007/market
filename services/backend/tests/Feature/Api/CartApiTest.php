<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use \Tests\Concerns\WarehouseInventoryFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureWarehouseInventoryFixtures();
    }

    use RefreshDatabase;

    public function test_cart_prefers_shipping_location_id_over_region_id(): void
    {
        $validLocation = ShippingLocation::factory()->create([
            'is_active' => true,
        ]);

        $invalidRegion = ShippingLocation::factory()->create([
            'is_active' => false,
        ]);

        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
            'price' => 1000,
        ]);

        $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(201);

        $response = $this->getJson('/api/v1/cart?shipping_location_id=' . $validLocation->id . '&region_id=' . $invalidRegion->id);

        $response->assertStatus(200)->assertJsonStructure([
            'items',
            'subtotal',
            'item_count',
            'is_empty',
        ]);
    }

    public function test_cart_reports_original_prices_and_savings_without_deducting_them_twice(): void
    {
        $discounted = Product::factory()->create([
            'state' => 'active', 'parent_product_id' => null,
            'price' => 1000, 'original_price' => 1500,
        ]);
        $regular = Product::factory()->create([
            'state' => 'active', 'parent_product_id' => null,
            'price' => 500, 'original_price' => null,
        ]);
        $this->postJson('/api/v1/cart', ['product_id' => $discounted->id, 'quantity' => 2])->assertCreated();
        $this->postJson('/api/v1/cart', ['product_id' => $regular->id, 'quantity' => 1])->assertCreated();

        $cart = $this->getJson('/api/v1/cart')->assertOk()
            ->assertJsonPath('subtotal', 2500)
            ->assertJsonPath('old_subtotal', 3500)
            ->assertJsonPath('discount_total', 1000);
        $items = collect($cart->json('items'))->keyBy('product_id');

        $this->assertEquals(1500, $items[$discounted->id]['old_price']);
        $this->assertEquals(3000, $items[$discounted->id]['old_total']);
        $this->assertEquals(1000, $items[$discounted->id]['discount_amount']);
        $this->assertEquals(2000, $items[$discounted->id]['total']);
        $this->assertNull($items[$regular->id]['old_price']);
        $this->assertSame(0, $items[$regular->id]['discount_amount']);
    }

    public function test_active_pricing_policy_creates_cart_savings_without_extra_discount(): void
    {
        $sku = Product::factory()->create([
            'state' => 'active', 'parent_product_id' => null,
            'price' => 1000, 'original_price' => null,
        ]);
        \App\Models\Pricing\PricingRule::create([
            'name' => 'Акция', 'is_active' => true, 'target_type' => 'all',
            'audience_type' => 'all', 'adjustment_type' => 'discount_percent',
            'amount' => 10, 'priority' => 0,
        ]);
        $this->postJson('/api/v1/cart', ['product_id' => $sku->id, 'quantity' => 1])->assertCreated();
        $this->getJson('/api/v1/cart')->assertOk()
            ->assertJsonPath('items.0.price', 900)
            ->assertJsonPath('items.0.old_price', 1000)
            ->assertJsonPath('items.0.discount_amount', 100)
            ->assertJsonPath('subtotal', 900)
            ->assertJsonPath('old_subtotal', 1000)
            ->assertJsonPath('discount_total', 100);
    }
}
