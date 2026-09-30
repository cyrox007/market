<?php

namespace Tests\Feature\Api;

use App\Models\Shipping\ShippingLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_regions_list_cached_and_flushed_on_region_change(): void
    {
        ShippingLocation::factory()->create(['name' => 'Москва', 'type' => 'region', 'is_active' => true]);

        // Прогрев кэша списка городов.
        $this->getJson('/api/v1/regions')->assertStatus(200);
        $this->assertTrue(Cache::has(ShippingLocation::REGIONS_CACHE_KEY));

        // Правка региона сбрасывает кэш.
        $region = ShippingLocation::where('type', 'region')->first();
        $region->name = 'Москва и область';
        $region->save();

        $this->assertFalse(Cache::has(ShippingLocation::REGIONS_CACHE_KEY));
    }

    public function test_non_region_change_keeps_regions_cache(): void
    {
        ShippingLocation::factory()->create(['name' => 'Москва', 'type' => 'region', 'is_active' => true]);

        $this->getJson('/api/v1/regions')->assertStatus(200);
        $this->assertTrue(Cache::has(ShippingLocation::REGIONS_CACHE_KEY));

        // Изменение записи НЕ типа region (город = locality) кэш городов не трогает.
        ShippingLocation::factory()->create(['name' => 'Химки', 'type' => 'locality', 'is_active' => true]);

        $this->assertTrue(Cache::has(ShippingLocation::REGIONS_CACHE_KEY));
    }
}

