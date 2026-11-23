<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\OneCOrdersAccessPage;
use App\Models\User;
use App\Services\Integration\OneCOrdersAccessTokens;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OneCOrdersAccessPageTest extends TestCase
{
    private function admin(string $role = 'super_admin'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));

        return $user;
    }

    public function test_only_super_admin_can_manage_onec_orders_credentials(): void
    {
        $this->admin('manager');
        $this->assertFalse(OneCOrdersAccessPage::canAccess());
        Livewire::test(OneCOrdersAccessPage::class)->assertForbidden();
        $this->assertSame(0, DB::table('onec_orders_access_tokens')->count());

        $this->admin('super_admin');
        $this->assertTrue(OneCOrdersAccessPage::canAccess());
        Livewire::test(OneCOrdersAccessPage::class)->assertSuccessful()
            ->assertSee('Доступ 1С к заказам');
    }

    public function test_issued_token_works_without_static_env_setting_and_can_be_revoked(): void
    {
        config([
            'onec_orders_read.enabled' => false,
            'onec_orders_read.token' => '',
        ]);
        $user = $this->admin();

        $component = Livewire::test(OneCOrdersAccessPage::class)
            ->set('name', '1С — рабочая база')
            ->call('issueToken')
            ->assertHasNoErrors()
            ->assertSee('Новый токен');
        $plaintext = $component->get('issuedToken');
        $id = $component->get('issuedTokenId');
        $this->assertIsString($plaintext);
        $this->assertSame(69, strlen($plaintext));
        $this->assertStringStartsWith('onec_', $plaintext);

        $row = DB::table('onec_orders_access_tokens')->find($id);
        $this->assertSame('1С — рабочая база', $row->name);
        $this->assertSame(hash('sha256', $plaintext), $row->token_hash);
        $this->assertStringNotContainsString($plaintext, json_encode((array) $row));
        $this->assertSame(['orders:read'], json_decode($row->abilities, true));
        $this->assertSame($user->id, $row->created_by);
        $this->assertSame('issued', DB::table('onec_orders_access_token_events')->first()->event);

        // Finish the creation component's actions before switching to an API
        // HTTP request; the Livewire testing snapshot is request-scoped.
        $component->call('dismissIssuedToken')->assertSet('issuedToken', null);

        $this->withHeader('X-1C-Orders-Token', $plaintext)
            ->getJson('/api/v1/integration/1c/orders')->assertOk();
        $used = DB::table('onec_orders_access_tokens')->find($id);
        $this->assertNotNull($used->last_used_at);
        $this->assertNotNull($used->last_used_ip);

        // Re-enter the web guard after calling the stateless API and mount a
        // fresh Filament component to revoke the issued credential.
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $revokeComponent = Livewire::test(OneCOrdersAccessPage::class)
            ->assertDontSee($plaintext)
            ->assertSee('Выданные токены')
            ->assertSee('1С — рабочая база');
        $revokeComponent->call('revokeToken', $id)->assertSuccessful();
        $this->assertNotNull(DB::table('onec_orders_access_tokens')->find($id)->revoked_at);
        // Idempotent revoke: no duplicate audit log.
        $revokeComponent->call('revokeToken', $id);
        $this->assertSame(2, DB::table('onec_orders_access_token_events')->count());
        $this->assertEqualsCanonicalizing(
            ['issued', 'revoked'],
            DB::table('onec_orders_access_token_events')->pluck('event')->all()
        );
        $this->assertDatabaseHas('onec_orders_access_tokens', [
            'id' => $id,
            'name' => '1С — рабочая база',
            'created_by' => $user->id,
        ]);

        $this->withHeader('X-1C-Orders-Token', $plaintext)
            ->getJson('/api/v1/integration/1c/orders')->assertUnauthorized();
    }

    public function test_expiry_ability_and_ip_restrictions_are_enforced_at_the_api(): void
    {
        config(['onec_orders_read.enabled' => false, 'onec_orders_read.token' => '']);
        $this->admin();

        $component = Livewire::test(OneCOrdersAccessPage::class)
            ->set('name', 'Тест 1С')
            ->set('allowedIps', '203.0.113.10, 203.0.113.11')
            ->call('issueToken')
            ->assertHasNoErrors();
        $secret = $component->get('issuedToken');
        $id = $component->get('issuedTokenId');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.12'])
            ->withHeader('X-1C-Orders-Token', $secret)
            ->getJson('/api/v1/integration/1c/orders')->assertUnauthorized();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('X-1C-Orders-Token', $secret)
            ->getJson('/api/v1/integration/1c/orders')->assertOk();

        DB::table('onec_orders_access_tokens')->where('id', $id)
            ->update(['abilities' => json_encode(['orders:status:write'])]);
        $this->withHeader('X-1C-Orders-Token', $secret)
            ->getJson('/api/v1/integration/1c/orders')->assertUnauthorized();

        DB::table('onec_orders_access_tokens')->where('id', $id)
            ->update(['abilities' => json_encode(['orders:read']),
                'expires_at' => now()->subMinute()]);
        $this->withHeader('X-1C-Orders-Token', $secret)
            ->getJson('/api/v1/integration/1c/orders')->assertUnauthorized();
    }

    public function test_invalid_ip_and_expired_input_are_rejected_without_persisting_tokens(): void
    {
        $this->admin();
        Livewire::test(OneCOrdersAccessPage::class)
            ->set('name', 'Неверный IP')
            ->set('allowedIps', 'not-an-ip')
            ->call('issueToken')
            ->assertHasErrors('allowedIps');
        $this->assertSame(0, DB::table('onec_orders_access_tokens')->count());

        Livewire::test(OneCOrdersAccessPage::class)
            ->set('name', 'Прошедшая дата')
            ->set('expiresOn', '2020-01-01')
            ->call('issueToken')
            ->assertHasErrors('expiresOn');
        $this->assertSame(0, DB::table('onec_orders_access_tokens')->count());
    }

    public function test_existing_env_credential_remains_opt_in_and_independent(): void
    {
        $this->admin();
        config([
            'onec_orders_read.enabled' => true,
            'onec_orders_read.token' => 'old-static-test-secret-32-characters-minimum',
        ]);
        $this->withHeader('X-1C-Orders-Token', config('onec_orders_read.token'))
            ->getJson('/api/v1/integration/1c/orders')->assertOk();

        $this->assertNull(app(OneCOrdersAccessTokens::class)->authenticate(
            'wrong', '127.0.0.1'
        ));
        config(['onec_orders_read.enabled' => false]);
        $this->withHeader('X-1C-Orders-Token', 'old-static-test-secret-32-characters-minimum')
            ->getJson('/api/v1/integration/1c/orders')->assertUnauthorized();
    }
}
