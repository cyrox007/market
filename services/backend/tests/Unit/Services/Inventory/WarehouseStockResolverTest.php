<?php

namespace Tests\Unit\Services\Inventory;

use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Models\Shipping\ShippingLocation;
use App\Services\Inventory\WarehouseStockResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseStockResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_global_stock_when_warehouse_mode_disabled(): void
    {
        $settings = ProductStockSettings::getInstance();
        $settings->warehouse_accounting_enabled = false;
        $settings->save();

        $product = Product::factory()->create(['stock' => 17]);
        $resolver = app(WarehouseStockResolver::class);

        $resolved = $resolver->resolveForProduct($product, null);

        $this->assertSame(17.0, $resolved);
    }

    public function test_it_resolves_stock_for_location_and_its_parent_binding(): void
    {
        $settings = ProductStockSettings::getInstance();
        $settings->warehouse_accounting_enabled = true;
        $settings->fallback_to_first_warehouse = true;
        $settings->save();

        $parent = ShippingLocation::factory()->create();
        $child = ShippingLocation::factory()->create(['parent_id' => $parent->id]);
        $warehouse = Warehouse::query()->create([
            'external_id' => '660e8400-e29b-41d4-a716-446655440001',
            'name' => 'Склад 1',
        ]);
        $warehouse->shippingLocations()->sync([$parent->id]);

        $product = Product::factory()->create(['stock' => 0]);
        ProductWarehouseStock::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 12.5,
        ]);

        $resolver = app(WarehouseStockResolver::class);
        $resolved = $resolver->resolveForProduct($product, $child);

        $this->assertSame(12.5, $resolved);
    }
}
