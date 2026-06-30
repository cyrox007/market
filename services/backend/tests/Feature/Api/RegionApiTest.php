<?php

namespace Tests\Feature\Api;

use App\Models\Shipping\ShippingLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_detect_uses_shipping_location_id_from_request(): void
    {
        $priorityLocation = ShippingLocation::factory()->create([
            'name' => 'Priority Location',
            'type' => 'region',
            'is_active' => true,
        ]);

        $legacyRegion = ShippingLocation::factory()->create([
            'name' => 'Legacy Region',
            'type' => 'region',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/regions/detect?shipping_location_id=' . $priorityLocation->id . '&region_id=' . $legacyRegion->id);

        $response->assertStatus(200)
            ->assertJsonPath('source', 'request_location')
            ->assertJsonPath('region.id', $priorityLocation->id)
            ->assertJsonPath('region.name', $priorityLocation->name);
    }
}

