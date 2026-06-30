<?php

namespace Tests\Feature\Api;

use App\Models\Address\Address;
use App\Models\Order\Order;
use App\Models\Shipping\ShippingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_addresses(): void
    {
        $user = User::factory()->create();
        Address::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/addresses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'city', 'street', 'house', 'is_default', 'full_address'],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_authenticated_user_can_create_address(): void
    {
        $user = User::factory()->create();
        $location = ShippingLocation::factory()->create(['type' => 'locality']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/addresses', [
                'title' => 'Дом',
                'city' => 'Москва',
                'street' => 'Ленина',
                'house' => '1',
                'apartment' => '10',
                'entrance' => '2',
                'shipping_location_id' => $location->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'address' => ['id', 'title', 'city', 'street', 'house', 'apartment', 'entrance'],
            ]);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'title' => 'Дом',
            'city' => 'Москва',
            'street' => 'Ленина',
            'house' => '1',
            'apartment' => '10',
            'entrance' => '2',
        ]);
    }

    public function test_authenticated_user_can_get_address_details(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'address' => ['id', 'title', 'city', 'street', 'house'],
            ])
            ->assertJson([
                'address' => [
                    'id' => $address->id,
                    'city' => $address->city,
                ],
            ]);
    }

    public function test_authenticated_user_can_update_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'city' => 'Москва',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/addresses/{$address->id}", [
                'city' => 'Санкт-Петербург',
                'street' => 'Невский проспект',
                'house' => '1',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'address' => [
                    'city' => 'Санкт-Петербург',
                    'street' => 'Невский проспект',
                ],
            ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $address->id,
            'city' => 'Санкт-Петербург',
        ]);
    }

    public function test_authenticated_user_can_delete_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Адрес успешно удален']);

        $this->assertDatabaseMissing('user_addresses', [
            'id' => $address->id,
        ]);
    }

    public function test_authenticated_user_can_delete_address_used_in_order(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Адрес успешно удален']);

        $this->assertDatabaseMissing('user_addresses', [
            'id' => $address->id,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'address_id' => null,
        ]);
    }

    public function test_authenticated_user_can_set_default_address(): void
    {
        $user = User::factory()->create();
        $address1 = Address::factory()->create([
            'user_id' => $user->id,
            'is_default' => true,
        ]);
        $address2 = Address::factory()->create([
            'user_id' => $user->id,
            'is_default' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/addresses/{$address2->id}/set-default");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Адрес установлен как основной']);

        // Проверяем, что первый адрес больше не по умолчанию
        $this->assertDatabaseHas('user_addresses', [
            'id' => $address1->id,
            'is_default' => false,
        ]);

        // Проверяем, что второй адрес теперь по умолчанию
        $this->assertDatabaseHas('user_addresses', [
            'id' => $address2->id,
            'is_default' => true,
        ]);
    }

    public function test_user_cannot_access_other_users_addresses(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1, 'sanctum')
            ->getJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(403);
    }

    public function test_user_cannot_update_other_users_addresses(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1, 'sanctum')
            ->putJson("/api/v1/addresses/{$address->id}", [
                'city' => 'Москва',
                'street' => 'Ленина',
                'house' => '1',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_delete_other_users_addresses(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1, 'sanctum')
            ->deleteJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_addresses(): void
    {
        $response = $this->getJson('/api/v1/addresses');

        $response->assertStatus(401);
    }

    public function test_address_creation_requires_city_street_house(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/addresses', [
                'title' => 'Дом',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['city', 'street', 'house']);
    }

    public function test_address_list_shows_default_first(): void
    {
        $user = User::factory()->create();
        $address1 = Address::factory()->create([
            'user_id' => $user->id,
            'is_default' => false,
        ]);
        $address2 = Address::factory()->create([
            'user_id' => $user->id,
            'is_default' => true,
        ]);
        $address3 = Address::factory()->create([
            'user_id' => $user->id,
            'is_default' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/addresses');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals($address2->id, $data[0]['id']); // Адрес по умолчанию должен быть первым
    }
}
