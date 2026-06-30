<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use App\Models\Product\ProductRegionRule;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_index_excludes_hidden_items_for_location(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'region',
            'is_active' => true,
        ]);

        $visibleProduct = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $hiddenProduct = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        ProductRegionRule::query()->create([
            'product_id' => $hiddenProduct->id,
            'shipping_location_id' => $location->id,
            'is_hidden' => true,
            'is_active' => true,
            'priority' => 100,
        ]);

        $response = $this->getJson('/api/v1/products?shipping_location_id=' . $location->id);

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($visibleProduct->id));
        $this->assertFalse($ids->contains($hiddenProduct->id));
    }
}

