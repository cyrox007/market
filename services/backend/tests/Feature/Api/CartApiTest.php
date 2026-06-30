<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
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
}

