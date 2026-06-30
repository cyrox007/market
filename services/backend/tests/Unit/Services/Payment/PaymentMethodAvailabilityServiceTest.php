<?php

namespace Tests\Unit\Services\Payment;

use App\Models\Payment\PaymentMethod;
use App\Models\Shipping\ShippingLocation;
use App\Services\Payment\PaymentMethodAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentMethodAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentMethodAvailabilityService();
    }

    /** @test */
    public function it_returns_all_payment_methods_when_location_is_null()
    {
        PaymentMethod::factory()->count(3)->create([
            'is_active' => true,
            'is_enabled' => true,
        ]);

        $methods = $this->service->getAvailablePaymentMethods(null);

        $this->assertCount(3, $methods);
    }

    /** @test */
    public function it_returns_payment_methods_for_location()
    {
        $location = ShippingLocation::factory()->create();
        $paymentMethod1 = PaymentMethod::factory()->create(['is_active' => true, 'is_enabled' => true]);
        $paymentMethod2 = PaymentMethod::factory()->create(['is_active' => true, 'is_enabled' => true]);

        // Привязываем метод оплаты к локации
        $location->paymentMethods()->attach($paymentMethod1->id, [
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $methods = $this->service->getAvailablePaymentMethods($location);

        $this->assertCount(1, $methods);
        $this->assertEquals($paymentMethod1->id, $methods->first()->id);
    }

    /** @test */
    public function it_inherits_payment_methods_from_parent_location()
    {
        $parentLocation = ShippingLocation::factory()->create();
        $childLocation = ShippingLocation::factory()->create([
            'parent_id' => $parentLocation->id,
        ]);

        $paymentMethod = PaymentMethod::factory()->create(['is_active' => true, 'is_enabled' => true]);

        // Привязываем метод оплаты к родительской локации
        $parentLocation->paymentMethods()->attach($paymentMethod->id, [
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $methods = $this->service->getAvailablePaymentMethods($childLocation);

        $this->assertCount(1, $methods);
        $this->assertEquals($paymentMethod->id, $methods->first()->id);
    }

    /** @test */
    public function it_filters_inactive_payment_methods()
    {
        $location = ShippingLocation::factory()->create();
        $activeMethod = PaymentMethod::factory()->create(['is_active' => true, 'is_enabled' => true]);
        $inactiveMethod = PaymentMethod::factory()->create(['is_active' => false, 'is_enabled' => true]);

        $location->paymentMethods()->attach($activeMethod->id, ['is_active' => true]);
        $location->paymentMethods()->attach($inactiveMethod->id, ['is_active' => false]);

        $methods = $this->service->getAvailablePaymentMethods($location);

        $this->assertCount(1, $methods);
        $this->assertEquals($activeMethod->id, $methods->first()->id);
    }

    /** @test */
    public function it_checks_if_payment_method_is_available()
    {
        $location = ShippingLocation::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['is_active' => true, 'is_enabled' => true]);

        // Метод не привязан к локации
        $this->assertTrue($this->service->isPaymentMethodAvailable($paymentMethod, $location));

        // Привязываем метод к локации
        $location->paymentMethods()->attach($paymentMethod->id, ['is_active' => true]);
        $this->assertTrue($this->service->isPaymentMethodAvailable($paymentMethod, $location));

        // Деактивируем связь
        $location->paymentMethods()->updateExistingPivot($paymentMethod->id, ['is_active' => false]);
        // При отсутствии активной привязки сервис использует fallback и разрешает метод.
        $this->assertTrue($this->service->isPaymentMethodAvailable($paymentMethod, $location));
    }
}
