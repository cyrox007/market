<?php

namespace Tests\Unit\Services\Shipping;

use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\DeliveryHandlingType;
use App\Services\Shipping\ShippingCostCalculator;
use App\Services\Shipping\CarrierService;
use App\Services\Shipping\ShippingCalculationService;
use App\Services\Shipping\DeliveryHandlingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingCostCalculatorWithHandlingTest extends TestCase
{
    use RefreshDatabase;

    private ShippingCostCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $carrierService = app(CarrierService::class);
        $shippingCalculationService = app(ShippingCalculationService::class);
        $deliveryHandlingProvider = new DeliveryHandlingProvider();
        
        $this->calculator = new ShippingCostCalculator(
            $carrierService,
            $shippingCalculationService,
            $deliveryHandlingProvider
        );
    }

    /** @test */
    public function it_adds_handling_price_to_delivery_price()
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 500.0,
        ]);

        $handlingType = DeliveryHandlingType::factory()->create([
            'name' => 'Ручной подъем',
            'code' => 'manual',
            'is_active' => true,
        ]);

        $location->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 200.0,
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateForLocation(
            $location,
            1000.0,
            $handlingType
        );

        // Проверяем, что цена доставки и обработки правильно складываются
        $this->assertEquals(500.0, $result->deliveryPrice);
        $this->assertEquals(200.0, $result->handlingPrice);
        $this->assertEquals(700.0, $result->getDeliveryTotal()); // доставка + обработка
    }

    /** @test */
    public function it_adds_handling_price_with_floor_to_delivery_price()
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 500.0,
        ]);

        $handlingType = DeliveryHandlingType::factory()->create([
            'name' => 'Ручной подъем',
            'code' => 'manual',
            'requires_floor' => true,
            'max_floor' => 10,
            'is_active' => true,
        ]);

        $location->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 200.0,
            'floor_prices' => json_encode([1 => 200, 2 => 250, 3 => 300]),
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateForLocation(
            $location,
            1000.0,
            $handlingType,
            2 // 2 этаж
        );

        // Проверяем, что цена доставки и обработки правильно складываются
        $this->assertEquals(500.0, $result->deliveryPrice);
        $this->assertEquals(250.0, $result->handlingPrice); // цена для 2 этажа
        $this->assertEquals(750.0, $result->getDeliveryTotal()); // доставка + обработка
    }

    /** @test */
    public function it_inherits_handling_price_from_parent_location()
    {
        $parentLocation = ShippingLocation::factory()->create([
            'delivery_price' => 500.0,
        ]);
        $childLocation = ShippingLocation::factory()->create([
            'parent_id' => $parentLocation->id,
            'delivery_price' => null, // Наследуется от родителя
        ]);

        $handlingType = DeliveryHandlingType::factory()->create([
            'name' => 'Ручной подъем',
            'code' => 'manual',
            'is_active' => true,
        ]);

        // Привязываем тип обработки к родительской локации
        $parentLocation->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 200.0,
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateForLocation(
            $childLocation,
            1000.0,
            $handlingType
        );

        // Дочерняя локация должна наследовать цену обработки от родителя
        $this->assertEquals(500.0, $result->deliveryPrice); // наследуется от родителя
        $this->assertEquals(200.0, $result->handlingPrice); // наследуется от родителя
        $this->assertEquals(700.0, $result->getDeliveryTotal());
    }

    /** @test */
    public function it_combines_delivery_handling_and_assembly_prices()
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 500.0,
            'assembly_price' => 1000.0,
            'requires_assembly' => true,
        ]);

        $handlingType = DeliveryHandlingType::factory()->create([
            'name' => 'Ручной подъем',
            'code' => 'manual',
            'is_active' => true,
        ]);

        $location->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 200.0,
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateForLocation(
            $location,
            1000.0,
            $handlingType,
            null,
            true // requires_assembly
        );

        // Проверяем, что все цены правильно складываются
        $this->assertEquals(500.0, $result->deliveryPrice);
        $this->assertEquals(200.0, $result->handlingPrice);
        $this->assertEquals(1000.0, $result->assemblyPrice);
        $this->assertEquals(1700.0, $result->getTotal()); // доставка + обработка + сборка
    }

    /** @test */
    public function it_applies_free_delivery_threshold_before_adding_handling()
    {
        $location = ShippingLocation::factory()->create([
            'delivery_price' => 500.0,
            'free_delivery_threshold' => 5000.0,
        ]);

        $handlingType = DeliveryHandlingType::factory()->create([
            'name' => 'Ручной подъем',
            'code' => 'manual',
            'is_active' => true,
        ]);

        $location->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 200.0,
            'is_active' => true,
        ]);

        // Сумма заказа меньше порога - доставка платная
        $result1 = $this->calculator->calculateForLocation(
            $location,
            3000.0,
            $handlingType
        );
        $this->assertEquals(500.0, $result1->deliveryPrice);
        $this->assertEquals(200.0, $result1->handlingPrice);
        $this->assertEquals(700.0, $result1->getDeliveryTotal());

        // Сумма заказа больше порога - доставка бесплатная, но обработка платная
        $result2 = $this->calculator->calculateForLocation(
            $location,
            6000.0,
            $handlingType
        );
        $this->assertEquals(0.0, $result2->deliveryPrice); // бесплатная доставка
        $this->assertEquals(200.0, $result2->handlingPrice); // обработка все еще платная
        $this->assertEquals(200.0, $result2->getDeliveryTotal()); // только обработка
    }
}
