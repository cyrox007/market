<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use App\Services\Inventory\WarehouseStockResolver;
use Tests\Concerns\ProductColorFixtures;
use Tests\Concerns\WarehouseInventoryFixtures;
use Tests\TestCase;

class CartColorOptionsTest extends TestCase
{
    use ProductColorFixtures;
    use WarehouseInventoryFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureWarehouseInventoryFixtures();
        WarehouseStockResolver::clearCache();
    }

    public function test_variant_is_added_and_switched_by_color(): void
    {
        $parent = Product::factory()->create(['is_variable' => true, 'state' => 'active', 'parent_product_id' => null]);
        [$white, $black] = array_map(function (string $color) use ($parent) {
            $variant = Product::factory()->create(['parent_product_id' => $parent->id, 'state' => 'active', 'price' => 1000, 'stock' => 5]);
            $this->colorRow($variant, $color);

            return $variant;
        }, ['white', 'black']);

        $itemId = $this->postJson('/api/v1/cart', ['product_id' => $parent->id, 'quantity' => 1, 'color_options' => ['main' => 'black']])
            ->assertCreated()->json('item.id');
        $this->assertSame($black->id, $this->getJson('/api/v1/cart')->json('items.0.product_id'));

        $this->putJson("/api/v1/cart/{$itemId}", ['quantity' => 1, 'color_options' => ['main' => 'white']])->assertOk();
        $this->assertSame($white->id, $this->getJson('/api/v1/cart')->json('items.0.product_id'));

        $this->postJson('/api/v1/cart', ['product_id' => $parent->id, 'color_options' => ['main' => 'red']])->assertNotFound();
    }
}
