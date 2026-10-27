<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LegacyShippingRemovalTest extends TestCase
{
    public function test_legacy_routes_are_removed_and_directory_and_quote_remain(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->all();
        foreach (['api/v1/regions', 'api/v1/regions/tree', 'api/v1/regions/detect',
            'api/v1/shipping/locations', 'api/v1/shipping/calculate', 'api/v1/shipping/carriers',
            'api/v1/shipping/shipping-methods', 'api/v1/shipping/shipping-methods/calculate',
            'api/v1/shipping/delivery-handling-types', 'api/v1/shipping/additional-services'] as $uri) {
            $this->assertNotContains($uri, $uris);
        }
        $this->assertContains('api/v1/localities', $uris);
        $this->assertContains('api/v1/address-directory/{level}', $uris);
        $this->assertContains('api/v1/address-directory/hierarchy/{externalId}', $uris);
        $this->assertContains('api/v1/shipping/quote', $uris);
        $this->assertFileExists(app_path('Filament/Resources/Shipping/ShippingLocations/ShippingLocationResource.php'));
        $this->assertFileExists(app_path('Filament/Resources/RegionShippingMethods/RegionShippingMethodResource.php'));
    }
}
