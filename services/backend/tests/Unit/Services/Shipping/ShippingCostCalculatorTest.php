<?php

namespace Tests\Unit\Services\Shipping;

use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\DeliveryHandlingType;
use App\Services\Shipping\ShippingCostCalculator;
use App\Services\Shipping\CarrierService;
use App\Services\Shipping\ShippingCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Vanilo\Shipment\Models\ShippingMethod;

class ShippingCostCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private ShippingCostCalculator $calculator;
    private CarrierService $carrierService;
    private ShippingCalculationService $shippingCalculationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->carrierService = app(CarrierService::class);
        $this->shippingCalculationService = app(ShippingCalculationService::class);
        $this->calculator = new ShippingCostCalculator(
            $this->carrierService,
            $this->shippingCalculationService
        );
    }

    /** @test */
    public function it_calculates_shipping_cost_for_location_without_handling()
    {
        // Создаем локацию с базовой ценой доставки
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 500.0,
            'free_delivery_threshold' => 5000.0,
        ]);

        $result = $this->calculator->calculateForLocation($location, 1000.0);

        $this->assertEquals(500.0, $result->deliveryPrice);
        $this->assertNull($result->handlingPrice);
        $this->assertEquals(500.0, $result->getDeliveryTotal());
    }

    /** @test */
    public function it_applies_free_delivery_threshold()
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 500.0,
            'free_delivery_threshold' => 5000.0,
        ]);

        // Сумма заказа меньше порога
        $result1 = $this->calculator->calculateForLocation($location, 3000.0);
        $this->assertEquals(500.0, $result1->deliveryPrice);

        // Сумма заказа больше порога
        $result2 = $this->calculator->calculateForLocation($location, 6000.0);
        $this->assertEquals(0.0, $result2->deliveryPrice);
    }

    /** @test */
    public function it_calculates_shipping_cost_with_handling()
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 500.0,
        ]);

        $handlingType = DeliveryHandlingType::factory()->create([
            'requires_floor' => true,
            'max_floor' => 10,
        ]);

        // Настраиваем цену обработки для локации
        $location->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 200.0,
            'floor_prices' => json_encode([1 => 200, 2 => 250, 3 => 300]),
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateForLocation($location, 1000.0, $handlingType, 2);

        $this->assertEquals(500.0, $result->deliveryPrice);
        $this->assertEquals(250.0, $result->handlingPrice);
        $this->assertEquals(750.0, $result->getDeliveryTotal());
    }

    /** @test */
    public function it_calculates_shipping_cost_with_assembly()
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 500.0,
            'assembly_price' => 1000.0,
            'requires_assembly' => true,
        ]);

        $result = $this->calculator->calculateForLocation($location, 1000.0, null, null, true);

        $this->assertEquals(500.0, $result->deliveryPrice);
        $this->assertEquals(1000.0, $result->assemblyPrice);
        $this->assertEquals(1500.0, $result->getTotal());
    }

    /** @test */
    public function it_calculates_shipping_cost_for_method()
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 500.0,
        ]);

        $shippingMethod = ShippingMethod::create([
            'configuration' => [
                'base_price' => 600.0,
                'free_delivery_threshold' => 3000.0,
                'delivery_days_min' => 1,
                'delivery_days_max' => 3,
            ],
            'name' => 'Тестовый метод доставки',
            'carrier_id' => null,
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateForMethod($shippingMethod, $location, 1000.0);

        $this->assertEquals(600.0, $result->deliveryPrice);
        $this->assertEquals(600.0, $result->basePrice);
        $this->assertEquals(3000.0, $result->freeDeliveryThreshold);
        $this->assertEquals(1, $result->deliveryDaysMin);
        $this->assertEquals(3, $result->deliveryDaysMax);
    }

    /** @test */
    public function it_applies_free_delivery_threshold_for_method()
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 500.0,
        ]);

        $shippingMethod = ShippingMethod::create([
            'configuration' => [
                'base_price' => 600.0,
                'free_delivery_threshold' => 3000.0,
            ],
            'name' => 'Тестовый метод доставки',
            'carrier_id' => null,
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateForMethod($shippingMethod, $location, 4000.0);

        $this->assertEquals(0.0, $result->deliveryPrice);
    }

    /** @test */
    public function it_inherits_location_properties()
    {
        $parentLocation = ShippingLocation::factory()->create([
            'delivery_price' => 400.0,
            'free_delivery_threshold' => 4000.0,
        ]);

        $childLocation = ShippingLocation::factory()->create([
            'parent_id' => $parentLocation->id,
            'delivery_price' => null, // Наследуется от родителя
            'free_delivery_threshold' => null,
        ]);

        $result = $this->calculator->calculateForLocation($childLocation, 1000.0);

        $this->assertEquals(400.0, $result->deliveryPrice);
        $this->assertEquals(4000.0, $result->freeDeliveryThreshold);
    }
}
