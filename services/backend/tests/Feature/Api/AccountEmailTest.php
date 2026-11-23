<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\AccountEmailChanged;
use App\Notifications\AccountEmailVerification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function token(): string
    {
        return Notification::sent(new AnonymousNotifiable, AccountEmailVerification::class)->last()->token;
    }

    private function requestChange(User $user, string $email = 'new@example.test'): string
    {
        $this->actingAs($user, 'web')->postJson('/api/v1/auth/email/change', [
            'email' => $email, 'current_password' => 'password',
        ])->assertOk()->assertJsonPath('status', 'confirmation_sent');
        return $this->token();
    }

    public function test_current_email_is_verified_once_without_blocking_login(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'web')->postJson('/api/v1/auth/email/verification')->assertOk();
        $token = $this->token();
        $this->assertDatabaseMissing('account_email_requests', ['token_hash' => $token]);
        Notification::assertSentOnDemand(AccountEmailVerification::class, fn ($n, $channels, $recipient) => $recipient->routes['mail'] === $user->email);
        $this->postJson('/api/v1/auth/email/confirm', ['token' => $token])->assertOk()->assertJsonPath('status', 'email_verified');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->postJson('/api/v1/auth/email/confirm', ['token' => $token])->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_email_change_waits_for_confirmation_and_invalidates_sessions_tokens_and_reset_links(): void
    {
        $user = User::factory()->create(['email' => 'old@example.test']);
        $token = $this->requestChange($user, ' NEW@Example.test ');
        $this->assertSame('old@example.test', $user->fresh()->email);
        $user->createToken('device');
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'old-reset', 'created_at' => now()]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->postJson('/api/v1/auth/email/confirm', ['token' => $token])->assertOk()->assertJsonPath('requires_login', true);
        $fresh = $user->fresh();
        $this->assertSame('new@example.test', $fresh->email);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertSame(1, (int) $fresh->auth_email_version);
        $this->assertSame(0, $fresh->tokens()->count());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'old@example.test']);
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => 'old@example.test', 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => 'new@example.test', 'password' => 'password'])->assertOk();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.is_email_verified', true);
        Notification::assertSentOnDemand(AccountEmailChanged::class, fn ($n, $channels, $recipient) => $recipient->routes['mail'] === 'old@example.test');
    }

    public function test_change_requires_authentication_password_and_unique_email(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'used@example.test']);
        $this->postJson('/api/v1/auth/email/change', ['email' => 'new@example.test', 'current_password' => 'password'])->assertUnauthorized();
        $this->actingAs($user, 'web')->postJson('/api/v1/auth/email/change', ['email' => 'new@example.test', 'current_password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->actingAs($user, 'web')->postJson('/api/v1/auth/email/change', ['email' => 'USED@example.test', 'current_password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('email');
        Notification::assertNothingSent();
    }

    public function test_uniqueness_is_checked_again_at_confirmation(): void
    {
        $user = User::factory()->create();
        $token = $this->requestChange($user);
        User::factory()->create(['email' => 'new@example.test']);
        $this->postJson('/api/v1/auth/email/confirm', ['token' => $token])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_resend_has_cooldown_and_replaces_old_link(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'web')->postJson('/api/v1/auth/email/verification')->assertOk();
        $old = $this->token();
        $this->actingAs($user, 'web')->postJson('/api/v1/auth/email/verification')->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->travel(61)->seconds();
        $this->actingAs($user, 'web')->postJson('/api/v1/auth/email/verification')->assertOk();
        $this->postJson('/api/v1/auth/email/confirm', ['token' => $old])->assertUnprocessable();
        $this->postJson('/api/v1/auth/email/confirm', ['token' => $this->token()])->assertOk();
    }

    public function test_expired_cancelled_and_password_invalidated_links_cannot_change_email(): void
    {
        $user = User::factory()->create();
        $token = $this->requestChange($user);
        $this->actingAs($user, 'web')->deleteJson('/api/v1/auth/email/change')->assertOk();
        $this->postJson('/api/v1/auth/email/confirm', ['token' => $token])->assertUnprocessable();
        $token = $this->requestChange($user);
        $this->travel(61)->minutes();
        $this->postJson('/api/v1/auth/email/confirm', ['token' => $token])->assertUnprocessable();
        $token = $this->requestChange($user);
        $user->password = 'different-password';
        $user->save();
        $this->postJson('/api/v1/auth/email/confirm', ['token' => $token])->assertUnprocessable();
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_pending_email_expiry_uses_utc_iso8601_instead_of_sql_datetime(): void
    {
        $user = User::factory()->create(['email' => 'old@example.test']);

        // With no pending change the existing nullable API contract is preserved.
        $this->actingAs($user, 'web')->getJson('/api/v1/auth/email')
            ->assertOk()
            ->assertJsonPath('pending_email', null)
            ->assertJsonPath('pending_expires_at', null);

        $this->requestChange($user, 'new@example.test');
        $storedExpiry = DB::table('account_email_requests')
            ->where('user_id', $user->id)
            ->where('purpose', 'change')
            ->value('expires_at');
        $this->assertNotNull($storedExpiry);

        // The admin UI may run in Moscow, but the API should never interpret
        // the stored timezone-less SQL timestamp as Moscow local time.
        config(['app.admin_timezone' => 'Europe/Moscow']);
        $response = $this->actingAs($user, 'web')->getJson('/api/v1/auth/email');
        $response->assertOk()->assertJsonPath('pending_email', 'new@example.test');

        $expiry = $response->json('pending_expires_at');
        $this->assertIsString($expiry);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $expiry);
        $this->assertSame(
            \Illuminate\Support\Carbon::parse((string) $storedExpiry, 'UTC')->toISOString(),
            $expiry
        );

        $this->actingAs($user, 'web')->deleteJson('/api/v1/auth/email/change')
            ->assertOk();
        $this->actingAs($user, 'web')->getJson('/api/v1/auth/email')
            ->assertOk()
            ->assertJsonPath('pending_expires_at', null);
    }

    public function test_old_verification_cannot_verify_a_changed_address_and_profile_cannot_bypass_flow(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'web')->postJson('/api/v1/auth/email/verification')->assertOk();
        $token = $this->token();
        $this->actingAs($user, 'web')->putJson('/api/v1/auth/profile', ['email' => 'bypass@example.test'])->assertUnprocessable();
        $user->email = 'admin-change@example.test';
        $user->save();
        $this->postJson('/api/v1/auth/email/confirm', ['token' => $token])->assertUnprocessable();
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
