<?php

namespace Tests\Feature\Api;

use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Inventory\Warehouse;
use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\WarehouseDeliveryMethod;
use App\Models\Shipping\WarehouseDeliveryZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Shipping\Carrier;
use Vanilo\Shipment\Models\ShippingMethod;

class ShippingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем демонстрационные carriers
        Carrier::create(['name' => 'Стандартная доставка', 'is_active' => true]);
        Carrier::create(['name' => 'Экспресс-доставка', 'is_active' => true]);
    }

    public function test_can_get_locations(): void
    {
        ShippingLocation::factory()->create([
            'type' => 'federal_district',
            'name' => 'Центральный округ',
            'slug' => 'central-district',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/shipping/locations?type=federal_district');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'type', 'pickup_notice'],
                ],
            ]);
    }

    public function test_can_get_location_tree(): void
    {
        $district = ShippingLocation::factory()->create([
            'type' => 'federal_district',
            'name' => 'Центральный округ',
            'slug' => 'central-district',
            'is_active' => true,
        ]);

        ShippingLocation::factory()->create([
            'parent_id' => $district->id,
            'type' => 'region',
            'name' => 'Московская область',
            'slug' => 'moscow-oblast',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/shipping/locations/tree');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name'],
                ],
            ]);

        // Проверяем, что есть дочерние элементы
        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // Находим созданный district в ответе
        $districtData = collect($data)->firstWhere('id', $district->id);
        $this->assertNotNull($districtData, 'District should be in response');

        // Проверяем, что есть дочерние элементы (Laravel сериализует связи как snake_case)
        $this->assertArrayHasKey('active_children', $districtData);
        $this->assertNotEmpty($districtData['active_children']);
    }

    public function test_can_get_delivery_handling_types(): void
    {
        DeliveryHandlingType::factory()->create([
            'name' => 'Лифт',
            'slug' => 'elevator',
            'code' => 'elevator',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/shipping/delivery-handling-types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'code'],
                ],
            ]);
    }

    public function test_can_calculate_shipping(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'name' => 'Москва',
            'slug' => 'moscow',
            'delivery_price' => 500,
            'free_delivery_threshold' => 10000,
            'delivery_days_min' => 1,
            'delivery_days_max' => 3,
            'requires_assembly' => true,
            'assembly_price' => 1000,
            'is_active' => true,
        ]);

        // По умолчанию сборка не включена (requires_assembly не передан)
        $response = $this->postJson('/api/v1/shipping/calculate', [
            'location_id' => $location->id,
            'order_amount' => 5000,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'available',
                'calculation' => [
                    'delivery_price',
                    'total',
                ],
                'location',
            ])
            ->assertJson([
                'available' => true,
            ]);

        $this->assertNull($response->json('calculation.assembly_price'));
        $this->assertIsBool((bool) $response->json('calculation.requires_assembly'));
    }

    public function test_calculate_shipping_includes_assembly_only_when_requested(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'name' => 'Москва',
            'slug' => 'moscow',
            'delivery_price' => 500,
            'requires_assembly' => true,
            'assembly_price' => 1000,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/shipping/calculate', [
            'location_id' => $location->id,
            'order_amount' => 5000,
            'requires_assembly' => true,
        ]);

        $response->assertStatus(200);
        $this->assertSame(1000.0, (float) $response->json('calculation.assembly_price'));
        $this->assertSame(true, (bool) $response->json('calculation.requires_assembly'));
        $this->assertSame(1500.0, (float) $response->json('calculation.total'));
    }

    public function test_calculate_shipping_requires_floor_when_handling_type_requires_floor(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'name' => 'Москва',
            'slug' => 'moscow',
            'delivery_price' => 500,
            'is_active' => true,
        ]);

        $handlingType = DeliveryHandlingType::factory()->create([
            'name' => 'Ручной подъем',
            'code' => 'manual',
            'requires_floor' => true,
            'is_active' => true,
        ]);

        $location->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 200,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/shipping/calculate', [
            'location_id' => $location->id,
            'order_amount' => 5000,
            'delivery_handling_type_id' => $handlingType->id,
            // floor отсутствует
        ])->assertStatus(422);
    }

    public function test_can_get_location_info(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'name' => 'Москва',
            'slug' => 'moscow',
            'delivery_price' => 500,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/shipping/locations/{$location->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'type',
                    'effective_delivery_price',
                    'carriers',
                    'shipping_methods',
                ],
            ]);
    }

    public function test_can_get_carriers(): void
    {
        Carrier::create(['name' => 'DHL', 'is_active' => true]);

        $response = $this->getJson('/api/v1/shipping/carriers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'is_active'],
                ],
            ]);
    }

    public function test_can_get_shipping_methods_for_location(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'name' => 'Москва',
            'slug' => 'moscow',
            'delivery_price' => 500,
            'is_active' => true,
        ]);

        $carrier = Carrier::create(['name' => 'Стандартная доставка', 'is_active' => true]);

        // Привязываем carrier к локации
        $location->carriers()->attach($carrier->id, [
            'base_price' => 500,
            'free_delivery_threshold' => 10000,
            'delivery_days_min' => 1,
            'delivery_days_max' => 3,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/shipping/shipping-methods?location_id=' . $location->id . '&order_amount=5000');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'carrier',
                        'base_price',
                        'price',
                        'delivery_days_min',
                        'delivery_days_max',
                        'priority',
                        'source',
                    ],
                ],
                'shipping_resolution',
                'location',
            ]);

        $this->assertSame('carrier', $response->json('shipping_resolution'));
    }

    public function test_get_shipping_methods_uses_location_fallback_when_no_carriers(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'name' => 'Тамбов',
            'slug' => 'tambov',
            'delivery_price' => 750,
            'free_delivery_threshold' => null,
            'delivery_days_min' => 2,
            'delivery_days_max' => 5,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/shipping/shipping-methods?location_id=' . $location->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('location_fallback', $response->json('shipping_resolution'));
        $this->assertSame(750.0, (float) $response->json('data.0.base_price'));
        $this->assertSame('location_fallback', $response->json('data.0.source'));
    }

    public function test_get_shipping_methods_applies_free_delivery_threshold_to_price(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'name' => 'Москва',
            'slug' => 'moscow',
            'delivery_price' => 500,
            'is_active' => true,
        ]);

        $carrier = Carrier::create(['name' => 'Стандартная доставка', 'is_active' => true]);
        $location->carriers()->attach($carrier->id, [
            'base_price' => 500,
            'free_delivery_threshold' => 10000,
            'delivery_days_min' => 1,
            'delivery_days_max' => 3,
            'is_active' => true,
        ]);

        $belowThreshold = $this->getJson(
            '/api/v1/shipping/shipping-methods?location_id=' . $location->id . '&order_amount=5000'
        );
        $belowThreshold->assertStatus(200);
        $methods = $belowThreshold->json('data');
        $this->assertNotEmpty($methods);
        $this->assertGreaterThan(0, (float) ($methods[0]['price'] ?? 0));

        $aboveThreshold = $this->getJson(
            '/api/v1/shipping/shipping-methods?location_id=' . $location->id . '&order_amount=15000'
        );
        $aboveThreshold->assertStatus(200);
        $methodsAbove = $aboveThreshold->json('data');
        $this->assertNotEmpty($methodsAbove);
        $this->assertSame(0.0, (float) ($methodsAbove[0]['price'] ?? -1));
    }

    public function test_can_calculate_shipping_method_price(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'name' => 'Москва',
            'slug' => 'moscow',
            'delivery_price' => 500,
            'free_delivery_threshold' => 10000,
            'requires_assembly' => true,
            'assembly_price' => 1000,
            'is_active' => true,
        ]);

        $carrier = Carrier::create(['name' => 'Стандартная доставка', 'is_active' => true]);

        $shippingMethod = ShippingMethod::create([
            'name' => 'Стандартная доставка - Москва',
            'carrier_id' => $carrier->id,
            'configuration' => [
                'base_price' => 500,
                'free_delivery_threshold' => 10000,
                'delivery_days_min' => 1,
                'delivery_days_max' => 3,
                'location_id' => $location->id,
            ],
            'is_active' => true,
        ]);

        // По умолчанию сборка не включается
        $response = $this->postJson('/api/v1/shipping/shipping-methods/calculate', [
            'shipping_method_id' => $shippingMethod->id,
            'location_id' => $location->id,
            'order_amount' => 5000,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'shipping_method' => ['id', 'name', 'carrier'],
                'calculation' => [
                    'delivery_price',
                    'total',
                ],
                'delivery_days',
            ]);

        $this->assertNull($response->json('calculation.assembly_price'));
    }

    public function test_free_delivery_threshold_works(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'name' => 'Москва',
            'slug' => 'moscow',
            'delivery_price' => 500,
            'free_delivery_threshold' => 10000,
            'is_active' => true,
        ]);

        // Заказ меньше порога - должна быть платная доставка
        $response1 = $this->postJson('/api/v1/shipping/calculate', [
            'location_id' => $location->id,
            'order_amount' => 5000,
        ]);

        $response1->assertStatus(200)
            ->assertJson([
                'calculation' => [
                    'delivery_price' => 500,
                ],
            ]);

        // Заказ больше порога - должна быть бесплатная доставка
        $response2 = $this->postJson('/api/v1/shipping/calculate', [
            'location_id' => $location->id,
            'order_amount' => 15000,
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'calculation' => [
                    'delivery_price' => 0,
                ],
            ]);
    }

    public function test_carrier_inheritance_works(): void
    {
        $district = ShippingLocation::factory()->create([
            'type' => 'federal_district',
            'name' => 'Центральный округ',
            'slug' => 'central-district',
            'is_active' => true,
        ]);

        $location = ShippingLocation::factory()->create([
            'parent_id' => $district->id,
            'type' => 'locality',
            'name' => 'Москва',
            'slug' => 'moscow',
            'is_active' => true,
        ]);

        $carrier = Carrier::create(['name' => 'Стандартная доставка', 'is_active' => true]);

        // Привязываем carrier к родительской локации
        $district->carriers()->attach($carrier->id, [
            'base_price' => 500,
            'is_active' => true,
        ]);

        // Дочерняя локация должна наследовать carrier
        $effectiveCarriers = $location->getEffectiveCarriers();
        $this->assertNotEmpty($effectiveCarriers);
        $this->assertEquals($carrier->id, $effectiveCarriers->first()->id);
    }
    public function test_can_get_warehouse_delivery_options_for_location(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'name' => 'Москва',
            'slug' => 'moscow-delivery-options',
            'delivery_price' => 900,
            'delivery_days_min' => 3,
            'delivery_days_max' => 5,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'external_id' => '55555555-5555-5555-5555-555555555555',
            'name' => 'Склад Москва',
            'is_active' => true,
        ]);

        $carrier = Carrier::create([
            'name' => 'Собственная доставка',
            'is_active' => true,
        ]);

        $shippingMethod = ShippingMethod::create([
            'name' => 'Курьер со склада',
            'carrier_id' => $carrier->id,
            'configuration' => [],
            'is_active' => true,
        ]);

        $deliveryMethod = WarehouseDeliveryMethod::create([
            'warehouse_id' => $warehouse->id,
            'shipping_method_id' => $shippingMethod->id,
            'is_active' => true,
            'priority' => 100,
        ]);

        WarehouseDeliveryZone::create([
            'warehouse_delivery_method_id' => $deliveryMethod->id,
            'shipping_location_id' => $location->id,
            'delivery_price' => 550,
            'delivery_days_min' => 1,
            'delivery_days_max' => 2,
            'is_active' => true,
            'priority' => 10,
        ]);

        $response = $this->getJson(
            '/api/v1/shipping/delivery-options?location_id='
            . $location->id
            . '&order_amount=3000'
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.0.warehouse_delivery_method_id', $deliveryMethod->id)
            ->assertJsonPath('data.0.warehouse_id', $warehouse->id)
            ->assertJsonPath('data.0.shipping_method_id', $shippingMethod->id)
            ->assertJsonPath('data.0.shipping_method_name', 'Курьер со склада')
            ->assertJsonPath('data.0.carrier.name', 'Собственная доставка')
            ->assertJsonPath('data.0.delivery_price', 550)
            ->assertJsonPath('data.0.delivery_days_min', 1)
            ->assertJsonPath('data.0.delivery_days_max', 2)
            ->assertJsonPath('data.0.inherited', false);
    }
}
