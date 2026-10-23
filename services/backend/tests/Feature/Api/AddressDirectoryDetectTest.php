<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressDirectoryDetectTest extends TestCase
{
    public function test_guest_receives_candidates_from_gar_without_legacy_shipping_location(): void
    {
        $candidate = ['externalId' => 'eacb5f15-1a2e-432e-904a-ca56bd635f1b', 'name' => 'Липецк', 'distanceKm' => 0.2];
        Http::fake(['*' => Http::response(['data' => [$candidate]])]);
        $this->postJson('/api/v1/address-directory/detect', ['latitude' => 52.6, 'longitude' => 39.6])
            ->assertOk()->assertJsonPath('data.0.externalId', $candidate['externalId'])
            ->assertJsonMissingPath('shippingLocation');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v2/address/nearby')
            && (float) $request['latitude'] === 52.6 && (float) $request['longitude'] === 39.6);
    }

    public function test_invalid_coordinates_are_rejected_without_upstream_request(): void
    {
        Http::preventStrayRequests();
        $this->postJson('/api/v1/address-directory/detect', [])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->postJson('/api/v1/address-directory/detect', ['latitude' => 91, 'longitude' => -181])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_empty_results_and_upstream_failures_are_distinct(): void
    {
        Http::fakeSequence()->push(['data' => []])->push([], 500);
        $this->postJson('/api/v1/address-directory/detect', ['latitude' => 0, 'longitude' => 0])->assertOk()->assertJsonPath('data', []);
        $this->postJson('/api/v1/address-directory/detect', ['latitude' => 0, 'longitude' => 0])->assertStatus(503);
    }

    public function test_timeout_does_not_clear_a_customer_selection(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $this->postJson('/api/v1/address-directory/detect', ['latitude' => 52.6, 'longitude' => 39.6])->assertStatus(503);
    }
}
