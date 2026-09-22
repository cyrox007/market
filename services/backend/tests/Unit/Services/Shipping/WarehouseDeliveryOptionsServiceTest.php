<?php

namespace Tests\Unit\Services\Shipping;

use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Models\Shipping\ShippingLocation;
use App\Services\Shipping\WarehouseDeliveryOptionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseDeliveryOptionsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_multiple_warehouse_options_for_location(): void
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 900,
            'delivery_days_min' => 3,
            'delivery_days_max' => 5,
        ]);

        $first = Warehouse::create([
            'external_id' => '11111111-1111-1111-1111-111111111111',
            'name' => 'Склад Москва',
            'is_active' => true,
        ]);
        $second = Warehouse::create([
            'external_id' => '22222222-2222-2222-2222-222222222222',
            'name' => 'Склад Тверь',
            'is_active' => true,
        ]);

        $first->shippingLocations()->attach($location->id, [
            'delivery_price' => 500,
            'delivery_days_min' => 1,
            'delivery_days_max' => 2,
            'is_active' => true,
            'priority' => 100,
        ]);
        $second->shippingLocations()->attach($location->id, [
            'delivery_price' => 700,
            'delivery_days_min' => 2,
            'delivery_days_max' => 3,
            'is_active' => true,
            'priority' => 50,
        ]);

        $options = app(WarehouseDeliveryOptionsService::class)->resolveForLocation($location);

        $this->assertCount(2, $options);
        $this->assertSame($first->id, $options[0]['warehouse_id']);
        $this->assertSame(500.0, $options[0]['delivery_price']);
        $this->assertSame(1, $options[0]['delivery_days_min']);
        $this->assertFalse($options[0]['inherited']);
    }

    public function test_exact_location_rule_overrides_parent_rule_for_same_warehouse(): void
    {
        $parent = ShippingLocation::factory()->create([
            'type' => 'region',
            'delivery_price' => 1000,
        ]);
        $location = ShippingLocation::factory()->create([
            'parent_id' => $parent->id,
            'type' => 'locality',
            'delivery_price' => null,
        ]);

        $warehouse = Warehouse::create([
            'external_id' => '33333333-3333-3333-3333-333333333333',
            'name' => 'Основной склад',
            'is_active' => true,
        ]);

        $warehouse->shippingLocations()->attach($parent->id, [
            'delivery_price' => 800,
            'is_active' => true,
            'priority' => 100,
        ]);
        $warehouse->shippingLocations()->attach($location->id, [
            'delivery_price' => 600,
            'is_active' => true,
            'priority' => 10,
        ]);

        $option = app(WarehouseDeliveryOptionsService::class)
            ->resolveForLocation($location)
            ->first();

        $this->assertSame(600.0, $option['delivery_price']);
        $this->assertFalse($option['inherited']);
        $this->assertSame($location->id, $option['location_id']);
    }

    public function test_legacy_link_without_rule_values_uses_location_defaults(): void
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 450,
            'delivery_days_min' => 2,
            'delivery_days_max' => 4,
        ]);

        $warehouse = Warehouse::create([
            'external_id' => '44444444-4444-4444-4444-444444444444',
            'name' => 'Legacy склад',
            'is_active' => true,
        ]);

        $warehouse->shippingLocations()->attach($location->id);

        $option = app(WarehouseDeliveryOptionsService::class)
            ->resolveForLocation($location)
            ->first();

        $this->assertSame(450.0, $option['delivery_price']);
        $this->assertSame(2, $option['delivery_days_min']);
        $this->assertSame(4, $option['delivery_days_max']);
    }

    public function test_it_filters_out_warehouse_without_enough_stock_for_all_items(): void
    {
        ProductStockSettings::getInstance()->update([
            'warehouse_accounting_enabled' => true,
            'fallback_to_first_warehouse' => false,
        ]);

        $location = ShippingLocation::factory()->create([
            'delivery_price' => 900,
            'delivery_days_min' => 3,
            'delivery_days_max' => 5,
        ]);

        $product = Product::factory()->create([
            'state' => 'active',
            'stock' => 10,
        ]);

        $enough = Warehouse::create([
            'external_id' => '66666666-6666-6666-6666-666666666666',
            'name' => 'Склад с остатком',
            'is_active' => true,
        ]);
        $notEnough = Warehouse::create([
            'external_id' => '77777777-7777-7777-7777-777777777777',
            'name' => 'Склад без остатка',
            'is_active' => true,
        ]);

        foreach ([$enough, $notEnough] as $warehouse) {
            $warehouse->shippingLocations()->attach($location->id, [
                'delivery_price' => 500,
                'is_active' => true,
                'priority' => 10,
            ]);
        }

        ProductWarehouseStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $enough->id,
            'quantity' => 3,
        ]);
        ProductWarehouseStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $notEnough->id,
            'quantity' => 1,
        ]);

        $options = app(WarehouseDeliveryOptionsService::class)->resolveForLocation($location, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $this->assertCount(1, $options);
        $this->assertSame($enough->id, $options->first()['warehouse_id']);
    }


}
