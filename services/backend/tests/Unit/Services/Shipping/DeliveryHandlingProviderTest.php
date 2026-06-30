<?php

namespace Tests\Unit\Services\Shipping;

use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use App\Services\Shipping\DeliveryHandlingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryHandlingProviderTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryHandlingProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new DeliveryHandlingProvider();
    }

    /** @test */
    public function it_returns_handling_types_for_location()
    {
        $location = ShippingLocation::factory()->create();
        $handlingType = DeliveryHandlingType::factory()->create([
            'name' => 'Ручной подъем',
            'code' => 'manual',
            'requires_floor' => true,
            'is_active' => true,
        ]);

        // Привязываем тип обработки к локации
        $location->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 200.0,
            'is_active' => true,
        ]);

        $types = $this->provider->getAvailableHandlingTypes($location);

        $this->assertCount(1, $types);
        $this->assertEquals($handlingType->id, $types->first()->id);
    }

    /** @test */
    public function it_inherits_handling_types_from_parent_location()
    {
        $parentLocation = ShippingLocation::factory()->create();
        $childLocation = ShippingLocation::factory()->create([
            'parent_id' => $parentLocation->id,
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

        // Дочерняя локация должна наследовать тип обработки
        $types = $this->provider->getAvailableHandlingTypes($childLocation);

        $this->assertCount(1, $types);
        $this->assertEquals($handlingType->id, $types->first()->id);
    }

    /** @test */
    public function it_returns_handling_price_for_location()
    {
        $location = ShippingLocation::factory()->create();
        $handlingType = DeliveryHandlingType::factory()->create([
            'name' => 'Ручной подъем',
            'code' => 'manual',
            'is_active' => true,
        ]);

        $location->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 200.0,
            'is_active' => true,
        ]);

        $price = $this->provider->getHandlingPrice($location, $handlingType);

        $this->assertEquals(200.0, $price);
    }

    /** @test */
    public function it_returns_handling_price_with_floor()
    {
        $location = ShippingLocation::factory()->create();
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

        // Цена для 1 этажа
        $price1 = $this->provider->getHandlingPrice($location, $handlingType, 1);
        $this->assertEquals(200.0, $price1);

        // Цена для 2 этажа
        $price2 = $this->provider->getHandlingPrice($location, $handlingType, 2);
        $this->assertEquals(250.0, $price2);

        // Цена для 3 этажа
        $price3 = $this->provider->getHandlingPrice($location, $handlingType, 3);
        $this->assertEquals(300.0, $price3);
    }

    /** @test */
    public function it_inherits_handling_price_from_parent_location()
    {
        $parentLocation = ShippingLocation::factory()->create();
        $childLocation = ShippingLocation::factory()->create([
            'parent_id' => $parentLocation->id,
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

        // Дочерняя локация должна наследовать цену
        $price = $this->provider->getHandlingPrice($childLocation, $handlingType);

        $this->assertEquals(200.0, $price);
    }

    /** @test */
    public function it_prioritizes_child_location_price_over_parent()
    {
        $parentLocation = ShippingLocation::factory()->create();
        $childLocation = ShippingLocation::factory()->create([
            'parent_id' => $parentLocation->id,
        ]);

        $handlingType = DeliveryHandlingType::factory()->create([
            'name' => 'Ручной подъем',
            'code' => 'manual',
            'is_active' => true,
        ]);

        // Привязываем тип обработки к родительской локации с одной ценой
        $parentLocation->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 200.0,
            'is_active' => true,
        ]);

        // Привязываем тип обработки к дочерней локации с другой ценой
        $childLocation->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 300.0,
            'is_active' => true,
        ]);

        // Дочерняя локация должна использовать свою цену
        $price = $this->provider->getHandlingPrice($childLocation, $handlingType);

        $this->assertEquals(300.0, $price);
    }

    /** @test */
    public function it_returns_elevator_price_when_handling_type_is_elevator()
    {
        $location = ShippingLocation::factory()->create();
        $handlingType = DeliveryHandlingType::factory()->create([
            'name' => 'Лифт',
            'code' => 'elevator',
            'requires_elevator' => true,
            'is_active' => true,
        ]);

        $location->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 200.0,
            'elevator_price' => 150.0,
            'is_active' => true,
        ]);

        // Для типа обработки "лифт" должна возвращаться elevator_price
        $price = $this->provider->getHandlingPrice($location, $handlingType);

        $this->assertEquals(150.0, $price);
    }

    /** @test */
    public function it_checks_if_handling_type_is_available()
    {
        $location = ShippingLocation::factory()->create();
        $handlingType = DeliveryHandlingType::factory()->create([
            'name' => 'Ручной подъем',
            'code' => 'manual',
            'is_active' => true,
        ]);

        // Тип обработки не привязан к локации
        $this->assertFalse($this->provider->isHandlingTypeAvailable($location, $handlingType));

        // Привязываем тип обработки к локации
        $location->deliveryHandlingTypes()->attach($handlingType->id, [
            'base_price' => 200.0,
            'is_active' => true,
        ]);

        $this->assertTrue($this->provider->isHandlingTypeAvailable($location, $handlingType));
    }

    /** @test */
    public function it_filters_inactive_handling_types()
    {
        $location = ShippingLocation::factory()->create();
        $activeType = DeliveryHandlingType::factory()->create([
            'name' => 'Активный тип',
            'is_active' => true,
        ]);
        $inactiveType = DeliveryHandlingType::factory()->create([
            'name' => 'Неактивный тип',
            'is_active' => false,
        ]);

        $location->deliveryHandlingTypes()->attach($activeType->id, [
            'base_price' => 200.0,
            'is_active' => true,
        ]);
        $location->deliveryHandlingTypes()->attach($inactiveType->id, [
            'base_price' => 200.0,
            'is_active' => false,
        ]);

        $types = $this->provider->getAvailableHandlingTypes($location);

        $this->assertCount(1, $types);
        $this->assertEquals($activeType->id, $types->first()->id);
    }

    /** @test */
    public function it_combines_handling_price_with_delivery_price()
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

        // Получаем цену обработки
        $handlingPrice = $this->provider->getHandlingPrice($location, $handlingType);

        // Проверяем, что цена обработки складывается с ценой доставки
        $this->assertEquals(200.0, $handlingPrice);
        $this->assertEquals(500.0, $location->getEffectiveDeliveryPrice());
        
        // Итоговая стоимость = доставка + обработка
        $total = $location->getEffectiveDeliveryPrice() + $handlingPrice;
        $this->assertEquals(700.0, $total);
    }
}
