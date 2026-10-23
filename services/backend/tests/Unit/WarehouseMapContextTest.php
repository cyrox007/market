<?php

namespace Tests\Unit;

use App\Services\Address\AddressDirectoryClient;
use App\Services\Address\WarehouseMapContext;
use Mockery;
use Tests\TestCase;

class WarehouseMapContextTest extends TestCase
{
    public function test_region_uses_known_settlement_bounds_without_warehouse_coordinates(): void
    {
        $client = Mockery::mock(AddressDirectoryClient::class);
        $client->shouldReceive('hierarchy')->with('region')->once()->andReturn(['target' => ['kind' => 'region']]);
        $client->shouldReceive('options')->with('localities', ['parentExternalId' => 'region', 'limit' => 200])->once()->andReturn([
            ['latitude' => 51, 'longitude' => 39], ['latitude' => 53, 'longitude' => 41], ['latitude' => null, 'longitude' => null],
        ]);
        $context = (new WarehouseMapContext($client))->focus('region', 'region');
        $this->assertSame([[51, 39], [53, 41]], $context['bounds']);
        $this->assertArrayNotHasKey('latitude', $context);
        $this->assertSame('region', $context['level']);
    }

    public function test_locality_focuses_immediately_without_requesting_houses(): void
    {
        $client = Mockery::mock(AddressDirectoryClient::class);
        $client->shouldReceive('hierarchy')->with('city')->once()->andReturn(['target' => ['latitude' => '51.6', 'longitude' => '39.2']]);
        $context = (new WarehouseMapContext($client))->focus('locality', 'city');
        $this->assertSame([51.6, 39.2], $context['point']);
        $this->assertSame(12, $context['zoom']);
    }

    public function test_house_without_coordinates_uses_street_overview_not_a_false_marker(): void
    {
        $client = Mockery::mock(AddressDirectoryClient::class);
        $client->shouldReceive('hierarchy')->with('house')->once()->andReturn([
            'target' => ['kind' => 'building', 'latitude' => null, 'longitude' => null],
            'path' => [['kind' => 'street', 'externalId' => 'street']],
        ]);
        $client->shouldReceive('options')->with('buildings', ['parentExternalId' => 'street', 'limit' => 200])->once()->andReturn([
            ['latitude' => 51.6, 'longitude' => 39.2], ['latitude' => 51.7, 'longitude' => 39.3],
        ]);
        $context = (new WarehouseMapContext($client))->focus('building', 'house');
        $this->assertSame('street', $context['level']);
        $this->assertArrayHasKey('bounds', $context);
        $this->assertArrayNotHasKey('point', $context);
    }

    public function test_house_with_precise_coordinates_focuses_house_without_listing_neighbors(): void
    {
        $client = Mockery::mock(AddressDirectoryClient::class);
        $client->shouldReceive('hierarchy')->with('house')->once()->andReturn(['target' => [
            'kind' => 'building', 'latitude' => 51.6, 'longitude' => 39.2, 'coordinatePrecision' => 'building_centroid',
        ]]);
        $context = (new WarehouseMapContext($client))->focus('building', 'house');
        $this->assertSame('building', $context['level']);
        $this->assertSame(17, $context['zoom']);
    }

    public function test_street_with_no_known_houses_falls_back_to_its_city(): void
    {
        $client = Mockery::mock(AddressDirectoryClient::class);
        $client->shouldReceive('hierarchy')->with('street')->once()->andReturn([
            'target' => ['externalId' => 'street'], 'path' => [['kind' => 'locality', 'latitude' => 51.6, 'longitude' => 39.2]],
        ]);
        $client->shouldReceive('options')->once()->andReturn([]);
        $this->assertSame('locality', (new WarehouseMapContext($client))->focus('street', 'street')['level']);
    }

    public function test_cleared_selection_does_not_request_or_invent_a_point(): void
    {
        $client = Mockery::mock(AddressDirectoryClient::class);
        $this->assertNull((new WarehouseMapContext($client))->focus('region', null));
    }
}
