<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\User;
use App\Notifications\AccountEmailChanged;
use App\Notifications\AccountEmailVerification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AccountEmail
{
    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function status(User $user): array
    {
        $pending = DB::table('account_email_requests')->where('user_id', $user->id)
            ->where('original_email', $user->email)->where('password_fingerprint', hash('sha256', $user->password))
            ->where('purpose', 'change')->where('expires_at', '>', now())->first();
        return ['email' => $user->email, 'is_verified' => $user->email_verified_at !== null,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'pending_email' => $pending?->email,
            // Query Builder returns a timezone-less SQL datetime. The application
            // stores these expiry instants in UTC; tag that timezone explicitly
            // rather than depending on the server or administrator timezone.
            'pending_expires_at' => $pending
                ? Carbon::parse((string) $pending->expires_at, 'UTC')->toISOString()
                : null];
    }

    public function issue(User $user, ?string $newEmail = null, ?string $currentPassword = null): array
    {
        return DB::transaction(function () use ($user, $newEmail, $currentPassword): array {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $purpose = $newEmail === null ? 'verify' : 'change';
            $email = self::normalize($newEmail ?? $user->email);
            if ($purpose === 'verify' && $user->email_verified_at !== null) {
                return ['status' => 'already_verified', 'message' => 'Почта уже подтверждена.'];
            }
            if ($purpose === 'change') {
                if (! Hash::check((string) $currentPassword, $user->password)) {
                    throw ValidationException::withMessages(['current_password' => 'Текущий пароль неверен.']);
                }
                if ($email === self::normalize($user->email)) {
                    throw ValidationException::withMessages(['email' => 'Укажите другой адрес почты.']);
                }
                $this->assertUnique($email, $user->id);
            }
            $previous = DB::table('account_email_requests')->where('user_id', $user->id)->where('purpose', $purpose)->first();
            if ($previous && now()->diffInSeconds(\Illuminate\Support\Carbon::parse($previous->updated_at), true) < 60) {
                abort(response()->json(['code' => 'email_resend_cooldown', 'message' => 'Повторите отправку через минуту.',
                    'retry_after' => 60], 429)->header('Retry-After', '60'));
            }
            $token = Str::random(64);
            DB::table('account_email_requests')->updateOrInsert(['user_id' => $user->id, 'purpose' => $purpose], [
                'original_email' => $user->email, 'email' => $email, 'token_hash' => hash('sha256', $token),
                'password_fingerprint' => hash('sha256', $user->password),
                'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            Notification::route('mail', $email)->notify(new AccountEmailVerification($token, $purpose === 'change'));
            return ['status' => 'confirmation_sent', 'message' => 'Письмо подтверждения отправлено.', 'expires_in' => 3600];
        }, 3);
    }

    public function confirm(string $token): array
    {
        try {
            return DB::transaction(function () use ($token): array {
                $hash = hash('sha256', $token);
                $candidate = DB::table('account_email_requests')->where('token_hash', $hash)->first();
                if (! $candidate) { $this->invalidToken(); }
                // Same lock order as issuance/cancellation: user first, then request.
                $user = User::lockForUpdate()->find($candidate->user_id);
                $pending = DB::table('account_email_requests')->where('token_hash', $hash)->lockForUpdate()->first();
                if (! $user || ! $pending || now()->gte($pending->expires_at)
                    || $user->email !== $pending->original_email
                    || ! hash_equals($pending->password_fingerprint, hash('sha256', $user->password))) { $this->invalidToken(); }
                $change = $pending->purpose === 'change';
                $oldEmail = $user->email;
                if ($change) {
                    $this->assertUnique($pending->email, $user->id);
                    $user->email = $pending->email;
                    $user->remember_token = Str::random(60);
                    $user->save();
                }
                $user->email_verified_at = now();
                $user->save();
                if ($change) {
                    // The version middleware also rejects concurrent/stale sessions and non-database drivers.
                    if (config('session.driver') === 'database') {
                        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                            ->where('user_id', $user->id)->delete();
                    }
                    $user->tokens()->delete();
                    DB::table('password_reset_tokens')->whereIn('email', [$oldEmail, $user->email])->delete();
                    DB::table('account_email_requests')->where('user_id', $user->id)->delete();
                    Notification::route('mail', $oldEmail)->notify(new AccountEmailChanged($user->email));
                } else {
                    DB::table('account_email_requests')->where('id', $pending->id)->delete();
                }
                return ['status' => $change ? 'email_changed' : 'email_verified',
                    'message' => $change ? 'Почта изменена. Войдите с новым адресом.' : 'Почта подтверждена.',
                    'requires_login' => $change];
            }, 3);
        } catch (QueryException $e) {
            // Database uniqueness is the final arbiter when registration races confirmation.
            if (in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages(['email' => 'Этот адрес уже используется.']);
            }
            throw $e;
        }
    }

    public function cancel(User $user): void
    {
        DB::transaction(function () use ($user): void {
            User::lockForUpdate()->findOrFail($user->id);
            DB::table('account_email_requests')->where('user_id', $user->id)->where('purpose', 'change')->delete();
        });
    }

    private function assertUnique(string $email, int $userId): void
    {
        if (User::whereRaw('LOWER(email) = ?', [$email])->whereKeyNot($userId)->exists()) {
            throw ValidationException::withMessages(['email' => 'Этот адрес уже используется.']);
        }
    }

    private function invalidToken(): never
    {
        throw ValidationException::withMessages(['token' => 'Ссылка недействительна или срок её действия истёк.']);
    }
}
