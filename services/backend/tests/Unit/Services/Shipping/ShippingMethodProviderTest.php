<?php

namespace Tests\Unit\Services\Shipping;

use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\Carrier;
use App\Services\Shipping\ShippingMethodProvider;
use App\Services\Product\ProductRegionRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Vanilo\Shipment\Models\ShippingMethod;

class ShippingMethodProviderTest extends TestCase
{
    use RefreshDatabase;

    private ShippingMethodProvider $provider;
    private ProductRegionRuleService $regionRuleService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->regionRuleService = app(ProductRegionRuleService::class);
        $this->provider = new ShippingMethodProvider($this->regionRuleService);
    }

    /** @test */
    public function it_returns_available_shipping_methods_for_location()
    {
        $location = ShippingLocation::factory()->create();
        $carrier = Carrier::factory()->create(['is_active' => true]);

        // Привязываем carrier к локации
        $location->carriers()->attach($carrier->id, [
            'base_price' => 500.0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $methods = $this->provider->getAvailableShippingMethods($location);

        $this->assertGreaterThan(0, $methods->count());
    }

    /** @test */
    public function it_returns_method_info_with_correct_structure()
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 400.0,
            'free_delivery_threshold' => 3000.0,
        ]);

        $carrier = Carrier::factory()->create(['is_active' => true]);
        $location->carriers()->attach($carrier->id, [
            'base_price' => 500.0,
            'free_delivery_threshold' => 2000.0,
            'delivery_days_min' => 1,
            'delivery_days_max' => 3,
            'is_active' => true,
        ]);

        // Получаем методы доставки
        $methods = $this->provider->getAvailableShippingMethods($location);
        $method = $methods->first();

        if ($method) {
            $methodInfo = $this->provider->getMethodInfo($method, $location, 1000.0);

            $this->assertArrayHasKey('id', $methodInfo);
            $this->assertArrayHasKey('name', $methodInfo);
            $this->assertArrayHasKey('base_price', $methodInfo);
            $this->assertArrayHasKey('price', $methodInfo);
            $this->assertArrayHasKey('free_delivery_threshold', $methodInfo);
            $this->assertArrayHasKey('delivery_days_min', $methodInfo);
            $this->assertArrayHasKey('delivery_days_max', $methodInfo);
            $this->assertArrayHasKey('carrier', $methodInfo);
            $this->assertArrayHasKey('priority', $methodInfo);
            $this->assertArrayHasKey('source', $methodInfo);
        }
    }

    /** @test */
    public function it_returns_single_fallback_method_when_location_has_no_carriers(): void
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 320,
            'delivery_days_min' => 2,
            'delivery_days_max' => 4,
        ]);

        $methods = $this->provider->getAvailableShippingMethods($location);

        $this->assertCount(1, $methods);
        $info = $this->provider->getMethodInfo($methods->first(), $location, 0);
        $this->assertSame('location_fallback', $info['source']);
        $this->assertSame(320.0, $info['base_price']);
    }

    /** @test */
    public function it_applies_free_delivery_threshold_in_method_info()
    {
        $location = ShippingLocation::factory()->create();
        $carrier = Carrier::factory()->create(['is_active' => true]);

        $location->carriers()->attach($carrier->id, [
            'base_price' => 500.0,
            'free_delivery_threshold' => 2000.0,
            'is_active' => true,
        ]);

        $methods = $this->provider->getAvailableShippingMethods($location);
        $method = $methods->first();

        if ($method) {
            // Сумма меньше порога
            $info1 = $this->provider->getMethodInfo($method, $location, 1000.0);
            $this->assertEquals(500.0, $info1['price']);

            // Сумма больше порога
            $info2 = $this->provider->getMethodInfo($method, $location, 3000.0);
            $this->assertEquals(0.0, $info2['price']);
        }
    }
}
