<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSessionRecoveryTest extends TestCase
{
    public function test_guests_cannot_prolong_a_session(): void
    {
        $this->getJson('/admin_sv/session-status')->assertUnauthorized();
    }

    public function test_customers_cannot_use_admin_heartbeat(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->getJson('/admin_sv/session-status')->assertForbidden();
    }

    public function test_operator_heartbeat_returns_current_token_without_rotating_it(): void
    {
        Role::findOrCreate('manager', 'web');
        $user = User::factory()->create();
        $user->assignRole('manager');
        $this->actingAs($user, 'web')->withSession(['_token' => 'current-token'])
            ->getJson('/admin_sv/session-status', ['X-CSRF-TOKEN' => 'current-token'])
            ->assertOk()->assertJsonPath('csrf_token', 'current-token')
            ->assertJsonPath('user_id', (string) $user->id)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSessionHas('_token', 'current-token');
        $this->actingAs($user, 'web')->withSession(['_token' => 'current-token'])
            ->getJson('/admin_sv/session-status', ['X-CSRF-TOKEN' => 'old-token'])
            ->assertOk()->assertJsonPath('csrf_token', 'current-token');
    }

    public function test_idle_operator_can_continue_after_two_hours_with_heartbeat(): void
    {
        Role::findOrCreate('manager', 'web');
        $user = User::factory()->create();
        $user->assignRole('manager');
        config(['session.driver' => 'database', 'session.lifetime' => 120]);
        \Illuminate\Support\Facades\Route::middleware('web')->post('/_test/operator-login', function () use ($user) {
            \Illuminate\Support\Facades\Auth::guard('web')->login($user);
            return response()->noContent();
        });
        $login = $this->post('/_test/operator-login')->assertNoContent();
        $this->withCookie(config('session.cookie'), $login->getCookie(config('session.cookie'))->getValue());
        $this->getJson('/admin_sv/session-status')->assertOk();
        for ($minute = 0; $minute < 180; $minute += 30) {
            $this->travel(30)->minutes();
            $this->getJson('/admin_sv/session-status')->assertOk();
        }
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_drafts_are_cleared_only_after_successful_form_save(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user, 'web');
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin_sv'));
        $product = \App\Models\Product\Product::factory()->create();
        \Livewire\Livewire::test(\App\Filament\Resources\Products\Pages\EditProduct::class, ['record' => $product->id])
            ->fillForm(['name' => 'Сохранённый черновик'])->call('saveAndStay')
            ->assertHasNoFormErrors()->assertDispatched('admin-form-saved');
        \Livewire\Livewire::test(\App\Filament\Resources\Products\Pages\EditProduct::class, ['record' => $product->id])
            ->fillForm(['name' => ''])->call('saveAndStay')
            ->assertHasFormErrors(['name'])->assertNotDispatched('admin-form-saved');
    }

    public function test_expired_page_has_clear_russian_instructions(): void
    {
        $html = view('errors.419')->render();
        $this->assertStringContainsString('Сеанс работы завершён', $html);
        $this->assertStringNotContainsString('PAGE EXPIRED', $html);
    }

    public function test_sleeping_operators_have_twelve_hours_but_public_sessions_do_not(): void
    {
        Role::findOrCreate('manager', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('manager');
        $buyer = User::factory()->create();
        config(['session.operator_lifetime' => 720]);
        $db = \Illuminate\Support\Facades\DB::connection();
        $handler = new \App\Session\OperatorDatabaseSessionHandler($db, 'sessions', 120, app());
        foreach (['operator' => $operator, 'buyer' => $buyer] as $id => $user) {
            $db->table('sessions')->insert(['id' => $id, 'user_id' => $user->id,
                'last_activity' => now()->subHours(3)->timestamp, 'payload' => base64_encode('saved-session')]);
        }
        $this->assertSame('saved-session', $handler->read('operator'));
        $this->assertSame('', $handler->read('buyer'));
        $handler->gc(120 * 60);
        $this->assertSame('saved-session', $handler->read('operator'));
        $operator->removeRole('manager');
        $this->assertSame('', $handler->read('operator'));
        $operator->assignRole('manager');
        $this->travel(10)->hours();
        $this->assertSame('', $handler->read('operator'));
    }

    public function test_workspace_script_and_per_user_encryption_config_render(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $html = view('filament.session-recovery')->render();
        $this->assertStringContainsString('adminWorkspaceConfig', $html);
        $this->assertStringContainsString('/js/admin-workspace.js', $html);
        $this->assertStringNotContainsString((string) config('app.key'), $html);
    }
}
