<?php
namespace Tests\Feature\Services;
use App\Services\Address\LocalityGeoDirectory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerLocalityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['address_directory.base_url' => 'https://address.test']);
        Http::preventStrayRequests();
    }
    public function test_search_uses_api_with_gar_parent_not_sqlite(): void
    {
        Http::fake(['address.test/*' => Http::response([['externalId' => 'city-guid', 'name' => 'Липецк']])]);
        $this->getJson('/api/v1/localities?region=region-guid&q='.urlencode('Лип'))
            ->assertOk()->assertJsonPath('data.0.externalId', 'city-guid');
        Http::assertSent(fn ($request) => $request['parentExternalId'] === 'region-guid' && $request['q'] === 'Лип');
    }
    public function test_search_and_geo_rate_limits_are_independent(): void
    {
        config(['address_directory.read_requests_per_minute' => 2, 'address_directory.geo_requests_per_minute' => 2]);
        Http::fake(['address.test/*' => Http::response(['data' => []])]);
        $this->getJson('/api/v1/localities?q=Ли')->assertOk();
        $this->getJson('/api/v1/localities?q=Ли')->assertOk();
        $this->getJson('/api/v1/localities?q=Ли')->assertStatus(429)->assertHeader('Retry-After');
        $point = ['latitude' => 52.6, 'longitude' => 39.6];
        $this->postJson('/api/v1/localities/detect', $point)->assertOk();
        $this->postJson('/api/v1/localities/detect', $point)->assertOk();
        $this->postJson('/api/v1/localities/detect', $point)->assertStatus(429)->assertHeader('Retry-After')->assertJsonStructure(['retry_after']);
    }

    public function test_geo_limit_does_not_block_manual_search(): void
    {
        config(['address_directory.read_requests_per_minute' => 2, 'address_directory.geo_requests_per_minute' => 1]);
        Http::fake(['address.test/*' => Http::response(['data' => []])]);
        $point = ['latitude' => 52.6, 'longitude' => 39.6];
        $this->postJson('/api/v1/localities/detect', $point)->assertOk();
        $this->postJson('/api/v1/localities/detect', $point)->assertStatus(429);
        $this->getJson('/api/v1/localities?q=Ли')->assertOk();
    }
    public function test_geo_preserves_api_confirmation_and_does_not_nest_data(): void
    {
        Http::fake(['address.test/*' => Http::response([
            'data' => [['externalId' => 'city-guid', 'distanceKm' => 0]],
            'meta' => ['requiresConfirmation' => true],
        ])]);
        $this->postJson('/api/v1/localities/detect', ['latitude' => 52.605, 'longitude' => 39.596])
            ->assertOk()->assertJsonPath('data.0.externalId', 'city-guid')
            ->assertJsonPath('meta.requiresConfirmation', true);
    }
    public function test_no_match_is_not_replaced_with_default_city(): void
    {
        Http::fake(['address.test/*' => Http::response(['data' => [], 'meta' => ['requiresConfirmation' => true]])]);
        $this->assertSame([], app(LocalityGeoDirectory::class)->nearby(0, 0)['data']);
    }
    public function test_invalid_coordinates_are_rejected_without_api_call(): void
    {
        $this->postJson('/api/v1/localities/detect', ['latitude' => 91, 'longitude' => 39])
            ->assertUnprocessable();
        Http::assertNothingSent();
    }
    public function test_guid_selection_keeps_region_guid_and_compatibility_code(): void
    {
        Http::fake(['address.test/*' => Http::response([
            'target' => ['kind' => 'locality', 'externalId' => 'city-guid', 'kladrCode' => '4800000100000'],
            'path' => [['kind' => 'region', 'externalId' => 'region-guid', 'kladrCode' => '4800000000000']],
        ])]);
        $result = app(LocalityGeoDirectory::class)->find('city-guid');
        $this->assertSame('region-guid', $result['regionExternalId']);
        $this->assertSame('4800000000000', $result['regionKladrCode']);
    }

    public function test_api_outage_is_not_a_successful_empty_directory(): void
    {
        Http::fake(['address.test/*' => Http::response([], 503)]);
        $this->getJson('/api/v1/localities/regions')->assertStatus(503);
    }
}

