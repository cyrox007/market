<?php

namespace Tests\Feature\Services;

use App\Models\Inventory\Warehouse;
use App\Services\Address\WarehouseAddressResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WarehouseAddressResolverTest extends TestCase
{
    private const GUID = 'afa660a0-0ca7-4577-afe4-234e5bb5d97a';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
    }

    private function api(array $target = []): void
    {
        Http::fake(['*/api/v2/address/hierarchy/*' => Http::response([
            'target' => array_merge(['kind' => 'building', 'source' => 'gar', 'externalId' => self::GUID,
                'name' => '10, корп. 2', 'kladrCode' => null, 'latitude' => null, 'longitude' => null], $target),
            'path' => [['kind' => 'locality', 'name' => 'Липецк', 'latitude' => 52.605, 'longitude' => 39.596]],
            'label' => 'Липецкая область, Липецк, Московская, д. 10, корп. 2',
        ])]);
    }

    public function test_persists_canonical_ids_and_snapshot_but_never_city_centre_as_warehouse_point(): void
    {
        $this->api(['kladrCode' => '4800000100003740001']);
        $data = app(WarehouseAddressResolver::class)->prepare(['name' => 'Склад', 'address_external_id' => self::GUID,
            'address' => 'Подменённый адрес', 'gar_guid' => 'forged']);
        $warehouse = Warehouse::create($data);
        $warehouse->refresh();
        $this->assertSame(self::GUID, $warehouse->gar_guid);
        $this->assertSame('4800000100003740001', $warehouse->kladr_code);
        $this->assertStringContainsString('корп. 2', $warehouse->address);
        $this->assertSame('building', $warehouse->address_snapshot['target']['kind']);
        $this->assertNull($warehouse->latitude);
        $this->assertNull($warehouse->coordinate_precision);
    }

    public function test_rejects_street_instead_of_building(): void
    {
        $this->api(['kind' => 'street']);
        $this->expectException(ValidationException::class);
        app(WarehouseAddressResolver::class)->prepare(['address_external_id' => self::GUID]);
    }

    public function test_unverified_classifier_source_is_rejected(): void
    {
        $this->api(['source' => 'unknown']);
        $this->expectException(ValidationException::class);
        app(WarehouseAddressResolver::class)->prepare(['address_external_id' => self::GUID]);
    }

    public function test_kladr_group_is_reduced_to_one_verified_house_number(): void
    {
        $id = '3600000100005630019';
        Http::fake(['*' => Http::response([
            'target' => ['kind' => 'building', 'source' => 'kladr', 'externalId' => $id, 'name' => '1,10,10д'],
            'path' => [['kind' => 'locality', 'name' => 'Воронеж']],
            'label' => 'Воронеж, Московский, ДОМ 1,10,10д',
        ])]);
        $data = app(WarehouseAddressResolver::class)->prepare(['address_external_id' => $id.'::10']);
        $this->assertSame($id, $data['address_external_id']);
        $this->assertNull($data['gar_guid']);
        $this->assertSame($id, $data['kladr_code']);
        $this->assertSame('Воронеж, Московский, ДОМ 10', $data['address']);
        $this->assertSame('10', $data['address_snapshot']['selectedBuildingNumber']);
    }

    public function test_kladr_group_cannot_save_an_invented_number(): void
    {
        $id = '3600000100005630019';
        Http::fake(['*' => Http::response([
            'target' => ['kind' => 'building', 'source' => 'kladr', 'externalId' => $id, 'name' => '1,10'],
            'path' => [['kind' => 'locality']], 'label' => 'Воронеж, ДОМ 1,10',
        ])]);
        $this->expectException(ValidationException::class);
        app(WarehouseAddressResolver::class)->prepare(['address_external_id' => $id.'::999']);
    }

    public function test_unchanged_address_can_be_saved_during_api_outage(): void
    {
        $record = new Warehouse(['address_external_id' => self::GUID, 'address' => 'Сохранённый адрес',
            'address_snapshot' => ['target' => ['externalId' => self::GUID]]]);
        $data = app(WarehouseAddressResolver::class)->prepare(['address_external_id' => self::GUID, 'address' => 'Подмена'], $record);
        $this->assertSame('Сохранённый адрес', $data['address']);
        Http::assertNothingSent();
    }

    public function test_changing_building_clears_stale_coordinates(): void
    {
        $this->api();
        $record = new Warehouse(['address_external_id' => 'old-guid', 'latitude' => 52.605, 'longitude' => 39.596]);
        $data = app(WarehouseAddressResolver::class)->prepare(['address_external_id' => self::GUID,
            'latitude' => '52.605', 'longitude' => '39.596'], $record);
        $this->assertNull($data['latitude']);
        $this->assertNull($data['longitude']);
    }

    public function test_manual_warehouse_point_is_explicitly_marked(): void
    {
        $this->api();
        $data = app(WarehouseAddressResolver::class)->prepare(['address_external_id' => self::GUID,
            'latitude' => 52.605, 'longitude' => 39.596]);
        $this->assertSame('operator', $data['coordinate_source']);
        $this->assertSame('warehouse_point', $data['coordinate_precision']);
    }

    public function test_coordinate_pair_is_required(): void
    {
        $this->expectException(ValidationException::class);
        app(WarehouseAddressResolver::class)->prepare(['latitude' => 52]);
    }

    public function test_api_outage_blocks_address_replacement(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $this->expectException(ValidationException::class);
        app(WarehouseAddressResolver::class)->prepare(['address_external_id' => self::GUID]);
    }
}
