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

    public function test_cart_reports_selected_multi_element_solution_not_a_sibling_color(): void
    {
        $parent = Product::factory()->create(['is_variable' => true, 'state' => 'active']);
        $chosen = Product::factory()->create(['parent_product_id' => $parent->id, 'state' => 'active', 'price' => 1000, 'stock' => 5]);
        $sibling = Product::factory()->create(['parent_product_id' => $parent->id, 'state' => 'active', 'price' => 1000, 'stock' => 5]);
        $this->colorRow($chosen, 'oak-white', 'fasad', ['oak', 'white']);
        $this->colorRow($chosen, 'graphite', 'korpus');
        $this->colorRow($sibling, 'black', 'fasad');

        $this->postJson('/api/v1/cart', ['product_id' => $chosen->id, 'quantity' => 1])->assertCreated();
        $item = $this->getJson('/api/v1/cart')->assertOk()->json('items.0');
        $this->assertSame($chosen->id, $item['product_id']);
        $this->assertSame(['fasad' => 'oak-white', 'korpus' => 'graphite'],
            collect($item['colors'])->mapWithKeys(fn ($row) => [$row['element']['slug'] => $row['solution']['slug']])->all());
        $this->assertSame(['oak', 'white'], array_column($item['colors'][0]['solution']['base_colors'], 'slug'));
        $this->assertArrayHasKey('icon_url', $item['colors'][0]['solution']);
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
