<?php

namespace Tests\Feature\Services;

use App\Services\Address\BuildingCoordinates;
use App\Services\Address\WarehouseAddressResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BuildingCoordinatesTest extends TestCase
{
    public function test_city_coordinates_are_not_building_coordinates(): void
    {
        config(['address_directory.building_points_path' => '/missing-building-points.sqlite']);
        $this->assertNull(app(BuildingCoordinates::class)->find([
            'target' => ['kind' => 'building', 'latitude' => 52.6, 'longitude' => 39.6, 'coordinatePrecision' => 'locality'],
        ]));
    }

    public function test_save_automatically_uses_verified_building_coordinates(): void
    {
        Http::fake(['*' => Http::response([
            'target' => ['kind' => 'building', 'source' => 'kladr', 'externalId' => '123', 'name' => '10',
                'latitude' => 52.6, 'longitude' => 39.6, 'coordinatePrecision' => 'building', 'coordinateSource' => 'osm'],
            'path' => [['kind' => 'locality', 'externalId' => 'city']], 'label' => 'Липецк, дом 10',
        ])]);
        $data = app(WarehouseAddressResolver::class)->prepare(['address_external_id' => '123', 'source_type' => 'physical']);
        $this->assertSame(52.6, $data['latitude']);
        $this->assertSame(39.6, $data['longitude']);
        $this->assertSame('osm', $data['coordinate_source']);
        $this->assertSame('building', $data['coordinate_precision']);
        $manual = app(WarehouseAddressResolver::class)->prepare(['address_external_id' => '123', 'latitude' => 52.61, 'longitude' => 39.61]);
        $this->assertSame('operator', $manual['coordinate_source']);
        $this->assertSame('warehouse_point', $manual['coordinate_precision']);
    }

    public function test_local_index_requires_city_identity_and_rejects_ambiguity(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'building-points-test-');
        try {
            $db = new \PDO('sqlite:'.$file);
            $db->exec('CREATE TABLE points (osm_id TEXT, street TEXT, house TEXT, city TEXT, latitude REAL, longitude REAL)');
            config(['address_directory.building_points_path' => $file]);
            $snapshot = ['target' => ['kind' => 'building', 'name' => '10'], 'path' => [
                ['kind' => 'locality', 'name' => 'Липецк', 'latitude' => 52.6, 'longitude' => 39.6],
                ['kind' => 'street', 'name' => 'улица Мира'],
            ]];
            $db->exec("INSERT INTO points VALUES ('1', 'мира', '10', NULL, 52.61, 39.61)");
            $service = app(BuildingCoordinates::class);
            $this->assertNull($service->find($snapshot));
            $db->exec("UPDATE points SET city = 'Липецк'");
            $this->assertSame(52.61, $service->find($snapshot)['latitude']);
            $db->exec("INSERT INTO points VALUES ('2', 'мира', '10', 'Липецк', 52.62, 39.62)");
            $this->assertNull($service->find($snapshot));
            $db = null;
        } finally {
            unlink($file);
        }
    }
}
