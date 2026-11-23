<?php

namespace Tests\Feature\Api;

use App\Models\Address\Address;
use App\Models\Order\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DefaultAddressBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake(['*/api/v2/address/hierarchy/*' => function ($request) {
            $id = basename($request->url());

            return Http::response([
                'target' => [
                    'kind' => 'building', 'externalId' => $id,
                    'name' => '1', 'source' => 'kladr', 'kladrCode' => $id,
                ],
                'path' => [
                    ['kind' => 'locality', 'name' => 'Москва'],
                    ['kind' => 'street', 'name' => 'Ленина'],
                ],
                'label' => 'Москва, Ленина, д. 1',
            ]);
        }]);
    }

    private function assertOneDefault(User $user, int $expectedId): void
    {
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
        $this->assertSame($expectedId, $user->addresses()->where('is_default', true)->value('id'));
    }

    public function test_first_created_address_is_default_even_if_requested_false(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/addresses', [
                'directory_external_id' => 'building-1',
                'title' => 'Дом',
                'is_default' => false,
            ])->assertCreated()->assertJsonPath('address.is_default', true)
            ->json('address.id');

        $this->assertOneDefault($user, $first);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/addresses')
            ->assertOk()->assertJsonPath('data.0.id', $first)->assertJsonPath('data.0.is_default', true);
    }

    public function test_creating_another_address_preserves_default_unless_selected_during_save(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/addresses', ['directory_external_id' => 'building-1'])
            ->assertCreated()->json('address.id');
        $second = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/addresses', [
                'directory_external_id' => 'building-2',
                'is_default' => false,
            ])->assertCreated()->assertJsonPath('address.is_default', false)->json('address.id');

        $this->assertOneDefault($user, $first);

        $third = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/addresses', [
                'directory_external_id' => 'building-3',
                'is_default' => true,
            ])->assertCreated()->assertJsonPath('address.is_default', true)->json('address.id');

        $this->assertOneDefault($user, $third);
        $this->assertDatabaseHas('user_addresses', ['id' => $first, 'is_default' => false]);
        $this->assertDatabaseHas('user_addresses', ['id' => $second, 'is_default' => false]);
    }

    public function test_edit_can_promote_address_atomically_without_separate_set_default_call(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $second = Address::factory()->create(['user_id' => $user->id, 'is_default' => false]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/addresses/{$second->id}", [
                'title' => 'Офис',
                'is_default' => true,
            ])->assertOk()->assertJsonPath('address.is_default', true)
            ->assertJsonPath('address.title', 'Офис');
        $this->assertOneDefault($user, $second->id);

        // The old endpoint must remain available for existing clients.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/addresses/{$first->id}/set-default")
            ->assertOk()->assertJsonPath('default_address_id', $first->id);
        $this->assertOneDefault($user, $first->id);
    }

    public function test_unchecking_current_primary_is_rejected_without_partial_save(): void
    {
        $user = User::factory()->create();
        $primary = Address::factory()->create([
            'user_id' => $user->id, 'is_default' => true, 'title' => 'Дом',
        ]);
        $other = Address::factory()->create(['user_id' => $user->id, 'is_default' => false]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/addresses/{$primary->id}", [
                'title' => 'Не должно сохраниться',
                'is_default' => false,
            ])->assertUnprocessable()->assertJsonValidationErrors('is_default');

        $this->assertDatabaseHas('user_addresses', [
            'id' => $primary->id, 'title' => 'Дом', 'is_default' => true,
        ]);
        $this->assertOneDefault($user, $primary->id);

        // Unchecking an address which is already secondary is a no-op.
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/addresses/{$other->id}", [
                'title' => 'Офис',
                'is_default' => false,
            ])->assertOk()->assertJsonPath('address.is_default', false);
        $this->assertOneDefault($user, $primary->id);
    }

    public function test_deleting_primary_assigns_oldest_remaining_address_and_keeps_order_snapshot(): void
    {
        $user = User::factory()->create();
        $primary = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $oldest = Address::factory()->create([
            'user_id' => $user->id, 'is_default' => false,
            'created_at' => now()->subDays(8),
        ]);
        $newest = Address::factory()->create([
            'user_id' => $user->id, 'is_default' => false,
            'created_at' => now()->subDay(),
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'address_id' => $primary->id,
            'address_snapshot' => 'Москва, Ленина, д. 1',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/addresses/{$primary->id}")
            ->assertOk()->assertJsonPath('default_address_id', $oldest->id);

        $this->assertOneDefault($user, $oldest->id);
        $this->assertDatabaseHas('user_addresses', ['id' => $newest->id, 'is_default' => false]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id, 'address_id' => null,
            'address_snapshot' => 'Москва, Ленина, д. 1',
        ]);
    }

    public function test_deleting_secondary_preserves_primary_and_deleting_last_returns_null(): void
    {
        $user = User::factory()->create();
        $main = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $other = Address::factory()->create(['user_id' => $user->id, 'is_default' => false]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/addresses/{$other->id}")
            ->assertOk()->assertJsonPath('default_address_id', $main->id);
        $this->assertOneDefault($user, $main->id);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/addresses/{$main->id}")
            ->assertOk()->assertJsonPath('default_address_id', null);
        $this->assertSame(0, $user->addresses()->count());
    }

    public function test_legacy_addresses_without_default_are_repaired_on_next_write(): void
    {
        $user = User::factory()->create();
        $old = Address::factory()->create([
            'user_id' => $user->id, 'is_default' => false,
            'created_at' => now()->subWeek(),
        ]);
        $new = Address::factory()->create([
            'user_id' => $user->id, 'is_default' => false,
            'created_at' => now(),
        ]);
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/addresses/{$new->id}", ['title' => 'Обновлён'])
            ->assertOk();
        $this->assertOneDefault($user, $old->id);
    }

    public function test_other_users_address_cannot_be_promoted_or_deleted(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $owner->id, 'is_default' => true]);

        $this->actingAs($intruder, 'sanctum')
            ->putJson("/api/v1/addresses/{$address->id}", ['is_default' => true])
            ->assertForbidden();
        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/v1/addresses/{$address->id}/set-default")
            ->assertForbidden();
        $this->actingAs($intruder, 'sanctum')
            ->deleteJson("/api/v1/addresses/{$address->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('user_addresses', ['id' => $address->id, 'is_default' => true]);
    }
}
