<?php

namespace Tests\Unit\Services\Shipping;

use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Models\Shipping\Carrier;
use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\WarehouseDeliveryMethod;
use App\Models\Shipping\WarehouseDeliveryZone;
use App\Services\Shipping\WarehouseDeliveryOptionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Vanilo\Shipment\Models\ShippingMethod;

class WarehouseDeliveryOptionsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_multiple_warehouse_and_method_options(): void
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 900,
            'delivery_days_min' => 3,
            'delivery_days_max' => 5,
        ]);

        $firstWarehouse = $this->createWarehouse(
            '11111111-1111-1111-1111-111111111111',
            'Склад Москва'
        );
        $secondWarehouse = $this->createWarehouse(
            '22222222-2222-2222-2222-222222222222',
            'Склад Тверь'
        );

        $firstMethod = $this->createDeliveryMethod($firstWarehouse, 'Курьер', 100);
        $secondMethod = $this->createDeliveryMethod($secondWarehouse, 'Транспортная компания', 50);

        $this->createZone($firstMethod, $location, 500, 1, 2);
        $this->createZone($secondMethod, $location, 700, 2, 3);

        $options = app(WarehouseDeliveryOptionsService::class)
            ->resolveForLocation($location);

        $this->assertCount(2, $options);
        $this->assertSame($firstWarehouse->id, $options[0]['warehouse_id']);
        $this->assertSame($firstMethod->id, $options[0]['warehouse_delivery_method_id']);
        $this->assertSame($firstMethod->shipping_method_id, $options[0]['shipping_method_id']);
        $this->assertSame(500.0, $options[0]['delivery_price']);
        $this->assertSame(1, $options[0]['delivery_days_min']);
        $this->assertFalse($options[0]['inherited']);
        $this->assertArrayHasKey('carrier', $options[0]);
    }

    public function test_exact_location_zone_overrides_parent_zone_for_same_method(): void
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

        $warehouse = $this->createWarehouse(
            '33333333-3333-3333-3333-333333333333',
            'Основной склад'
        );
        $method = $this->createDeliveryMethod($warehouse, 'Курьер');

        $this->createZone($method, $parent, 800, 2, 4, 100);
        $this->createZone($method, $location, 600, 1, 2, 10);

        $option = app(WarehouseDeliveryOptionsService::class)
            ->resolveForLocation($location)
            ->first();

        $this->assertSame(600.0, $option['delivery_price']);
        $this->assertFalse($option['inherited']);
        $this->assertSame($location->id, $option['location_id']);
    }

    public function test_it_applies_zone_free_delivery_threshold(): void
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 900,
            'free_delivery_threshold' => null,
        ]);
        $warehouse = $this->createWarehouse(
            '44444444-4444-4444-4444-444444444444',
            'Склад'
        );
        $method = $this->createDeliveryMethod($warehouse, 'Курьер');

        WarehouseDeliveryZone::create([
            'warehouse_delivery_method_id' => $method->id,
            'shipping_location_id' => $location->id,
            'delivery_price' => 650,
            'free_delivery_threshold' => 5000,
            'delivery_days_min' => 2,
            'delivery_days_max' => 4,
            'is_active' => true,
            'priority' => 0,
        ]);

        $below = app(WarehouseDeliveryOptionsService::class)
            ->resolveForLocation($location, [], 3000)
            ->first();
        $above = app(WarehouseDeliveryOptionsService::class)
            ->resolveForLocation($location, [], 6000)
            ->first();

        $this->assertSame(650.0, $below['delivery_price']);
        $this->assertSame(0.0, $above['delivery_price']);
        $this->assertSame(650.0, $above['delivery_base_price']);
        $this->assertSame(5000.0, $above['free_delivery_threshold']);
    }

    public function test_it_filters_out_warehouse_without_enough_stock_for_all_items(): void
    {
        ProductStockSettings::getInstance()->update([
            'warehouse_accounting_enabled' => true,
            'fallback_to_first_warehouse' => false,
        ]);

        $location = ShippingLocation::factory()->create();
        $product = Product::factory()->create([
            'state' => 'active',
            'stock' => 10,
        ]);

        $enough = $this->createWarehouse(
            '66666666-6666-6666-6666-666666666666',
            'Склад с остатком'
        );
        $notEnough = $this->createWarehouse(
            '77777777-7777-7777-7777-777777777777',
            'Склад без остатка'
        );

        $enoughMethod = $this->createDeliveryMethod($enough, 'Курьер 1', 10);
        $notEnoughMethod = $this->createDeliveryMethod($notEnough, 'Курьер 2', 10);
        $this->createZone($enoughMethod, $location, 500, 1, 2);
        $this->createZone($notEnoughMethod, $location, 500, 1, 2);

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

        $options = app(WarehouseDeliveryOptionsService::class)->resolveForLocation(
            $location,
            [['product_id' => $product->id, 'quantity' => 2]]
        );

        $this->assertCount(1, $options);
        $this->assertSame($enough->id, $options->first()['warehouse_id']);
    }

    private function createWarehouse(string $externalId, string $name): Warehouse
    {
        return Warehouse::create([
            'external_id' => $externalId,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function createDeliveryMethod(
        Warehouse $warehouse,
        string $name,
        int $priority = 0
    ): WarehouseDeliveryMethod {
        $carrier = Carrier::factory()->create(['is_active' => true]);

        $shippingMethod = ShippingMethod::create([
            'name' => $name,
            'carrier_id' => $carrier->id,
            'configuration' => [],
            'is_active' => true,
        ]);

        return WarehouseDeliveryMethod::create([
            'warehouse_id' => $warehouse->id,
            'shipping_method_id' => $shippingMethod->id,
            'is_active' => true,
            'priority' => $priority,
        ]);
    }

    private function createZone(
        WarehouseDeliveryMethod $method,
        ShippingLocation $location,
        float $price,
        int $daysMin,
        int $daysMax,
        int $priority = 0
    ): WarehouseDeliveryZone {
        return WarehouseDeliveryZone::create([
            'warehouse_delivery_method_id' => $method->id,
            'shipping_location_id' => $location->id,
            'delivery_price' => $price,
            'delivery_days_min' => $daysMin,
            'delivery_days_max' => $daysMax,
            'is_active' => true,
            'priority' => $priority,
        ]);
    }
}
