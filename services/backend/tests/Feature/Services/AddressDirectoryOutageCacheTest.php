<?php

namespace Tests\Feature\Services;

use App\Services\Address\AddressDirectoryClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class AddressDirectoryOutageCacheTest extends TestCase
{
    private const GUID = '0b470ebe-e6b5-4d02-8f24-12913786c077';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('address_directory.base_url', 'https://address.test');
        config()->set('address_directory.options_ttl', [
            'regions' => 30, 'localities' => 30, 'streets' => 30, 'buildings' => 30,
        ]);
        config()->set('address_directory.stale_ttl', 3600);
        config()->set('address_directory.search_stale_ttl', 3600);
        config()->set('address_directory.search_ttl', 30);
        config()->set('address_directory.stale_enabled', true);
    }

    public function test_fresh_regions_are_reused_without_another_upstream_request(): void
    {
        $items = [['externalId' => self::GUID, 'name' => 'Липецкая область']];
        Http::fake(['*' => Http::response($items)]);
        $client = app(AddressDirectoryClient::class);

        $this->assertSame($items, $client->options('regions'));
        $this->assertSame('live', $client->lastListCacheStatus());
        $this->assertSame($items, $client->options('regions'));
        $this->assertSame('fresh', $client->lastListCacheStatus());
        Http::assertSentCount(1);
    }

    public function test_last_good_cities_are_used_only_after_upstream_failure(): void
    {
        $items = [['externalId' => self::GUID, 'name' => 'Липецк']];
        Http::fakeSequence()->push($items)->push(['message' => 'down'], 503);
        $client = app(AddressDirectoryClient::class);
        $query = ['q' => 'Липецк', 'parentExternalId' => 'region-id'];

        $this->assertSame($items, $client->options('localities', $query));
        $this->travel(31)->seconds();
        $this->assertSame($items, $client->options('localities', array_reverse($query, true)));
        $this->assertSame('stale', $client->lastListCacheStatus());
        $this->assertGreaterThanOrEqual(31, $client->lastListCacheAge());
        Http::assertSentCount(2);
    }

    public function test_http_endpoint_flags_stale_lists_without_changing_json_data_contract(): void
    {
        $items = [['externalId' => self::GUID, 'name' => 'Москва']];
        Http::fakeSequence()->push($items)->push([], 500);
        $this->getJson('/api/v1/address-directory/regions')
            ->assertOk()->assertJsonPath('data.0.name', 'Москва');
        $this->travel(31)->seconds();
        $this->getJson('/api/v1/address-directory/regions')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Москва')
            ->assertHeader('X-Address-Directory-Cache', 'stale');
        Http::assertSentCount(2);
    }

    public function test_invalid_parent_is_not_hidden_by_stale_list(): void
    {
        Http::fakeSequence()->push([['externalId' => 'street', 'name' => 'Московская']])
            ->push(['error' => 'invalid parent'], 404);
        $client = app(AddressDirectoryClient::class);
        $client->options('streets', ['parentExternalId' => self::GUID]);
        $this->travel(31)->seconds();

        $this->expectException(ValidationException::class);
        $client->options('streets', ['parentExternalId' => self::GUID]);
    }

    public function test_expired_hierarchy_never_uses_outage_fallback(): void
    {
        config()->set('address_directory.cache_ttl', 1);
        Http::fakeSequence()
            ->push(['target' => ['kind' => 'building', 'externalId' => self::GUID]])
            ->push(['error' => 'down'], 503);
        $client = app(AddressDirectoryClient::class);
        $client->hierarchy(self::GUID);
        $this->travel(2)->seconds();

        $this->expectException(RuntimeException::class);
        $client->hierarchy(self::GUID);
    }

    public function test_empty_building_list_is_not_saved_as_last_good(): void
    {
        Http::fakeSequence()->push([])->push([], 503);
        $client = app(AddressDirectoryClient::class);
        $this->assertSame([], $client->options('buildings', ['parentExternalId' => self::GUID]));
        $this->expectException(RuntimeException::class);
        $client->options('buildings', ['parentExternalId' => self::GUID]);
    }

    public function test_stale_fallback_can_be_disabled_for_incident_response(): void
    {
        Http::fakeSequence()->push([['name' => 'Липецк']])->push([], 503);
        $client = app(AddressDirectoryClient::class);
        $client->options('regions');
        $this->travel(31)->seconds();
        config()->set('address_directory.stale_enabled', false);

        $this->expectException(RuntimeException::class);
        $client->options('regions');
    }

    public function test_no_successful_cache_still_returns_unavailable(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $this->getJson('/api/v1/address-directory/regions')->assertStatus(503);
    }
}
