<?php

namespace Tests\Unit;

use App\Services\Address\AddressHierarchy;
use App\Services\Address\FulfillmentAddress;
use Tests\TestCase;

class FulfillmentAddressTest extends TestCase
{
    public function test_federal_city_is_a_settlement_without_changing_its_identity(): void
    {
        $city = ['kind' => 'region', 'type' => 'г.', 'name' => 'Москва', 'externalId' => 'city-guid'];
        $this->assertSame($city, AddressHierarchy::locality(['path' => [$city]]));
        $this->assertFalse(AddressHierarchy::isLocality(['kind' => 'area', 'type' => 'г.о.']));
    }

    public function test_snapshot_keeps_codes_path_and_details_but_rejects_client_code_overrides(): void
    {
        $source = ['label' => 'Москва, дом 1', 'target' => ['externalId' => 'house-guid', 'source' => 'gar', 'objectId' => '123', 'kladrCode' => '770000000000001'], 'path' => [['externalId' => 'city-guid', 'kind' => 'region', 'type' => 'г.']]];
        $result = (new FulfillmentAddress)->snapshot($source, ['apartment' => '12', 'gar_guid' => 'forged']);
        $source['target']['externalId'] = 'changed';
        $this->assertSame('house-guid', $result['gar_guid']);
        $this->assertSame('123', $result['object_id']);
        $this->assertSame('770000000000001', $result['kladr_code']);
        $this->assertSame(['apartment' => '12'], $result['details']);
        $this->assertNull($result['latitude']);
        $this->assertSame('city-guid', $result['path'][0]['externalId']);
    }

    public function test_unknown_address_remains_unknown_instead_of_inventing_codes(): void
    {
        $this->assertNull((new FulfillmentAddress)->snapshot(null));
    }
}
