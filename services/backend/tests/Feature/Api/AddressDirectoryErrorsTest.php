<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressDirectoryErrorsTest extends TestCase
{
    private const ID = '00000000-0000-0000-0000-000000000000';

    public function test_catalog_reports_invalid_selection_for_upstream_client_errors(): void
    {
        foreach ([400, 404, 422] as $status) {
            Http::fake(['*' => Http::response(['message' => 'not found'], $status)]);
            $this->getJson('/api/v1/products?directory_external_id='.self::ID)
                ->assertUnprocessable()->assertJsonValidationErrors('directory_external_id');
        }
    }

    public function test_directory_endpoints_preserve_invalid_selection_errors(): void
    {
        Http::fake(['*' => Http::response(['message' => 'not found'], 404)]);
        $this->getJson('/api/v1/address-directory/hierarchy/'.self::ID)
            ->assertUnprocessable()->assertJsonValidationErrors('directory_external_id');
        $this->getJson('/api/v1/address-directory/localities?parentExternalId='.self::ID)
            ->assertUnprocessable()->assertJsonValidationErrors('parentExternalId');
    }

    public function test_service_failure_is_not_reported_as_invalid_selection(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $this->getJson('/api/v1/products?directory_external_id='.self::ID)->assertStatus(503);
        $this->getJson('/api/v1/address-directory/hierarchy/'.self::ID)->assertStatus(503);
    }

    public function test_timeout_is_still_unavailable_not_invalid_selection(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $this->getJson('/api/v1/products?directory_external_id='.self::ID)->assertStatus(503);
    }
}
