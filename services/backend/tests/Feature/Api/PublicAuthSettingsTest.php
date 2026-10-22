<?php

namespace Tests\Feature\Api;

use App\Filament\Pages\PublicAuthSettingsPage;
use App\Models\Settings\PublicAuthSettings;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicAuthSettingsTest extends TestCase
{
    private function registrationData(): array
    {
        return ['name' => 'Покупатель', 'email' => 'buyer@example.com', 'phone' => '+79991234567',
            'password' => 'password123', 'password_confirmation' => 'password123'];
    }

    public function test_public_settings_are_enabled_by_default_and_not_cached(): void
    {
        $this->getJson('/api/v1/auth/settings')->assertOk()->assertJsonPath('data.registration_enabled', true)
            ->assertJsonPath('data.login_enabled', true)->assertHeader('Cache-Control', 'no-store, private');
        PublicAuthSettings::current()->update(['registration_enabled' => false]);
        $this->getJson('/api/v1/auth/settings')->assertJsonPath('data.registration_enabled', false);
    }

    public function test_registration_disabled_blocks_api_without_creating_or_logging_in(): void
    {
        PublicAuthSettings::current()->update(['registration_enabled' => false]);
        $this->postJson('/api/v1/auth/register', $this->registrationData())->assertForbidden()
            ->assertJsonPath('code', 'public_registration_disabled');
        $this->assertDatabaseMissing('users', ['email' => 'buyer@example.com']);
        $this->assertGuest();
    }

    public function test_login_disabled_blocks_even_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        PublicAuthSettings::current()->update(['login_enabled' => false]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertForbidden()->assertJsonPath('code', 'public_login_disabled');
        $this->assertGuest();
    }

    public function test_registration_remains_possible_without_automatic_login(): void
    {
        PublicAuthSettings::current()->update(['login_enabled' => false]);
        $this->postJson('/api/v1/auth/register', $this->registrationData())->assertCreated()->assertJsonPath('authenticated', false);
        $this->assertDatabaseHas('users', ['email' => 'buyer@example.com']);
        $this->assertGuest();
    }

    public function test_existing_session_and_admin_access_are_preserved_and_login_can_be_reenabled(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password123'])->assertOk();
        PublicAuthSettings::current()->update(['login_enabled' => false, 'registration_enabled' => false]);
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertOk();
        PublicAuthSettings::current()->update(['login_enabled' => true]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password123'])->assertOk();
    }

    public function test_only_administrators_can_manage_switches(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->assertFalse(PublicAuthSettingsPage::canAccess());
        $role = Role::findOrCreate('admin', 'web');
        $user->assignRole($role);
        $this->assertTrue(PublicAuthSettingsPage::canAccess());
        PublicAuthSettings::current()->update(['login_enabled' => false]);
        $this->assertTrue($user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin_sv')));
    }

    public function test_admin_can_save_both_switches_from_the_page(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin_sv'));
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        \Livewire\Livewire::actingAs($admin)->test(PublicAuthSettingsPage::class)
            ->set('registrationEnabled', false)->set('loginEnabled', false)->call('save')->assertHasNoErrors();
        $this->assertFalse(PublicAuthSettings::current()->registration_enabled);
        $this->assertFalse(PublicAuthSettings::current()->login_enabled);
    }

    public function test_customer_cannot_open_the_settings_page(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin_sv'));
        \Livewire\Livewire::actingAs(User::factory()->create())->test(PublicAuthSettingsPage::class)->assertForbidden();
    }
}
