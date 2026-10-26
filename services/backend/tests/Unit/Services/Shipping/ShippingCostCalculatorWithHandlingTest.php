<?php

namespace Tests\Unit\Services\Shipping;

use Tests\TestCase;

class ShippingCostCalculatorWithHandlingTest extends TestCase
{
    public function test_legacy_shipping_services_are_removed(): void
    {
        foreach (['ShippingCostCalculator', 'ShippingCalculationService', 'LegacyWarehouseServices', 'DeliveryHandlingProvider', 'ShippingMethodProvider'] as $service) {
            $this->assertFileDoesNotExist(app_path('Services/Shipping/'.$service.'.php'));
        }
    }
}
