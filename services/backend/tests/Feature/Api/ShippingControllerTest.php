<?php

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShippingControllerTest extends TestCase
{
    public static function retiredRoutes(): array
    {
        return [
            ['GET', '/locations'], ['GET', '/locations/tree'], ['GET', '/locations/1'],
            ['GET', '/carriers'], ['GET', '/shipping-methods'],
            ['GET', '/delivery-handling-types'], ['GET', '/additional-services'],
            ['POST', '/calculate'], ['POST', '/shipping-methods/calculate'],
        ];
    }

    #[DataProvider('retiredRoutes')]
    public function test_old_shipping_endpoints_are_not_available(string $method, string $path): void
    {
        $this->json($method, '/api/v1/shipping'.$path, [])->assertNotFound();
    }
}
