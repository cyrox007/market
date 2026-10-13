<?php

namespace Tests\Feature\Api;

use App\Models\Address\Address;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryAddressTest extends TestCase
{
    private const GUID = 'afa660a0-0ca7-4577-afe4-234e5bb5d97a';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function directory(array $target = [], array $path = []): void
    {
        Http::fake(['*/api/v2/address/hierarchy/*' => Http::response([
            'target' => array_merge(['kind' => 'building', 'externalId' => self::GUID,
                'name' => '10, корп. 2, стр. 1', 'source' => 'gar', 'kladrCode' => null], $target),
            'path' => $path ?: [['kind' => 'region', 'name' => 'Липецкая область'], ['kind' => 'locality', 'name' => 'Липецк']],
            'label' => 'Липецкая область, Липецк, д. 10, корп. 2, стр. 1',
        ])]);
    }

    public function test_building_without_street_with_corpus_room_and_no_apartment_is_saved(): void
    {
        $this->directory();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/addresses', [
            'directory_external_id' => self::GUID, 'room' => '12', 'floor' => '2',
        ])->assertCreated()->assertJsonPath('address.gar_guid', self::GUID)
            ->assertJsonPath('address.street', '')->assertJsonPath('address.apartment', null)
            ->assertJsonPath('address.room', '12')
            ->assertJsonPath('address.full_address', 'Липецкая область, Липецк, д. 10, корп. 2, стр. 1, комн. 12');
        $this->assertDatabaseHas('user_addresses', ['user_id' => $user->id, 'gar_guid' => self::GUID, 'room' => '12']);
    }

    public function test_free_text_destination_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/addresses', [
            'city' => 'Выдуманный город', 'street' => 'Улица', 'house' => '1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['directory_external_id', 'city', 'street', 'house']);
    }

    public function test_a_single_house_can_be_selected_from_a_kladr_group(): void
    {
        $this->directory(['source' => 'kladr', 'name' => '1,10']);
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/addresses', [
            'directory_external_id' => self::GUID.'::10',
        ])->assertCreated()->assertJsonPath('address.house', '10');
    }

    public function test_a_street_cannot_be_saved_as_a_building(): void
    {
        $this->directory(['kind' => 'street']);
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/addresses', [
            'directory_external_id' => self::GUID,
        ])->assertUnprocessable()->assertJsonValidationErrors(['directory_external_id']);
    }

    public function test_details_can_be_cleared_without_directory_access(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id, 'directory_external_id' => self::GUID, 'room' => '12']);
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/addresses/'.$address->id, [
            'directory_external_id' => self::GUID, 'room' => null, 'apartment' => null,
        ])->assertOk()->assertJsonPath('address.room', null);
        Http::assertNothingSent();
    }

    public function test_directory_failure_does_not_create_an_address(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/addresses', [
            'directory_external_id' => self::GUID,
        ])->assertStatus(503);
        $this->assertDatabaseCount('user_addresses', 0);
    }

    public function test_children_require_a_parent_and_directory_errors_are_not_empty_successes(): void
    {
        $this->getJson('/api/v1/address-directory/streets')->assertUnprocessable();
        Http::fake(['*' => Http::response([], 503)]);
        $this->getJson('/api/v1/address-directory/regions')->assertStatus(503);
    }
}
