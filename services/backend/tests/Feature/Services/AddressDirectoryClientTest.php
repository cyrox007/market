<?php

namespace Tests\Feature\Services;

use App\Services\Address\AddressDirectoryClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressDirectoryClientTest extends TestCase
{
    public function test_it_removes_only_confirmed_padded_kladr_aliases(): void
    {
        Cache::flush();
        $city = ['source' => 'kladr', 'externalId' => '4800000100000', 'parentExternalId' => '4800000000000', 'name' => 'Липецк', 'type' => 'г'];
        $alias = array_replace($city, ['externalId' => '480000010000000', 'type' => 'город']);
        $otherRegion = array_replace($alias, ['externalId' => '530000010000000', 'parentExternalId' => '5300000000000']);
        Http::fake(['*' => Http::response([$alias, $city, $otherRegion])]);

        $this->assertSame([$city, $otherRegion], app(AddressDirectoryClient::class)->options('localities', ['q' => 'Липецк']));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('address_directory.base_url', 'https://address.test');
        config()->set('address_directory.search_path', '/api/v2/address/search');
        config()->set('address_directory.hierarchy_path', '/api/v2/address/hierarchy/{externalId}');
    }

    public function test_it_returns_only_buildings_for_a_warehouse_address(): void
    {
        Http::fake([
            'address.test/api/v2/address/search*' => Http::response([
                ['kind' => 'street', 'externalId' => 'street-1', 'label' => 'Липецк, ул. Московская'],
                ['kind' => 'building', 'externalId' => 'house-10', 'label' => 'Липецк, ул. Московская, д. 10'],
            ]),
        ]);

        $result = app(AddressDirectoryClient::class)->searchBuildings('Липецк Московская 10');

        $this->assertSame([
            ['kind' => 'building', 'externalId' => 'house-10', 'label' => 'Липецк, ул. Московская, д. 10'],
        ], $result);
        $this->assertSame(
            'Липецк, ул. Московская, д. 10',
            app(AddressDirectoryClient::class)->cachedLabel('house-10')
        );
    }

    public function test_it_resolves_the_saved_classifier_id_to_a_full_address(): void
    {
        Http::fake([
            'address.test/api/v2/address/hierarchy/house-10' => Http::response([
                'target' => ['kind' => 'building', 'externalId' => 'house-10'],
                'path' => [],
                'label' => 'Липецкая область, г. Липецк, ул. Московская, д. 10',
            ]),
        ]);

        $result = app(AddressDirectoryClient::class)->hierarchy('house-10');

        $this->assertSame('Липецкая область, г. Липецк, ул. Московская, д. 10', $result['label']);
    }
}
