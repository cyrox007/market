<?php

namespace App\Services\Integration;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

/**
 * Service credentials for the read-only 1C orders API.
 *
 * Plaintext is returned once on creation; only SHA-256 is persisted.
 * This does not use a customer Sanctum token or the outgoing ONEC_API_KEY.
 */
class OneCOrdersAccessTokens
{
    public const READ_ABILITY = 'orders:read';

    public const WRITE_STATUS_ABILITY = 'orders:status:write';

    /** @return array{token: string, id: int} */
    public function issue(
        string $name,
        ?string $expiresOn,
        array $allowedIps,
        int $actorId,
        ?string $actorIp = null,
        array $abilities = [self::READ_ABILITY]
    ): array {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Укажите название до 120 символов.']);
        }

        $expiresAt = $expiresOn === null || $expiresOn === ''
            ? null
            : Carbon::createFromFormat('!Y-m-d', $expiresOn, 'UTC')?->endOfDay();
        if ($expiresAt !== null && $expiresAt->lessThanOrEqualTo(now('UTC'))) {
            throw ValidationException::withMessages(['expiresOn' => 'Срок действия должен быть в будущем.']);
        }

        $allowedIps = array_values(array_unique(array_filter(array_map('trim', $allowedIps))));
        if (count($allowedIps) > 20) {
            throw ValidationException::withMessages(['allowedIps' => 'Допускается не более 20 IP-адресов.']);
        }
        foreach ($allowedIps as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                throw ValidationException::withMessages(['allowedIps' => 'Укажите IP-адреса через запятую, без масок CIDR.']);
            }
        }

        $abilities = array_values(array_unique($abilities));
        if ($abilities === [] || array_diff($abilities, [self::READ_ABILITY, self::WRITE_STATUS_ABILITY]) !== []) {
            throw ValidationException::withMessages(['abilities' => 'Недопустимые права токена 1С.']);
        }

        $plaintext = 'onec_'.bin2hex(random_bytes(32));
        $id = DB::transaction(function () use ($name, $expiresAt, $allowedIps, $actorId, $actorIp, $plaintext, $abilities): int {
            $id = DB::table('onec_orders_access_tokens')->insertGetId([
                'name' => $name,
                'token_hash' => hash('sha256', $plaintext),
                'token_hint' => substr($plaintext, 0, 12),
                'abilities' => json_encode($abilities, JSON_THROW_ON_ERROR),
                'allowed_ips' => json_encode($allowedIps, JSON_THROW_ON_ERROR),
                'expires_at' => $expiresAt,
                'created_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('onec_orders_access_token_events')->insert([
                'token_id' => $id,
                'actor_id' => $actorId,
                'event' => 'issued',
                'ip' => $actorIp,
                'created_at' => now(),
            ]);

            return $id;
        });

        return ['token' => $plaintext, 'id' => $id];
    }

    public function revoke(int $id, int $actorId, ?string $actorIp = null): bool
    {
        return DB::transaction(function () use ($id, $actorId, $actorIp): bool {
            $token = DB::table('onec_orders_access_tokens')->where('id', $id)->lockForUpdate()->first();
            if ($token === null || $token->revoked_at !== null) {
                return false;
            }

            DB::table('onec_orders_access_tokens')->where('id', $id)->update([
                'revoked_at' => now(),
                'revoked_by' => $actorId,
                'updated_at' => now(),
            ]);
            DB::table('onec_orders_access_token_events')->insert([
                'token_id' => $id,
                'actor_id' => $actorId,
                'event' => 'revoked',
                'ip' => $actorIp,
                'created_at' => now(),
            ]);

            return true;
        });
    }

    public function authenticate(string $plaintext, ?string $ip, string $requiredAbility = self::READ_ABILITY): ?stdClass
    {
        if (strlen($plaintext) !== 69 || ! str_starts_with($plaintext, 'onec_')) {
            return null;
        }

        $hash = hash('sha256', $plaintext);
        $record = DB::table('onec_orders_access_tokens')->where('token_hash', $hash)->first();
        if ($record === null || ! hash_equals($record->token_hash, $hash)
            || $record->revoked_at !== null
            || ($record->expires_at !== null
                && Carbon::parse($record->expires_at, 'UTC')->lessThanOrEqualTo(now('UTC')))) {
            return null;
        }

        $abilities = json_decode($record->abilities, true);
        $allowedIps = json_decode($record->allowed_ips ?? '[]', true);
        if (! is_array($abilities) || ! in_array($requiredAbility, $abilities, true)
            || ! is_array($allowedIps)
            || ($allowedIps !== [] && ($ip === null || ! in_array($ip, $allowedIps, true)))) {
            return null;
        }

        // Avoid a database write per read; still record useful last-use data.
        if ($record->last_used_at === null
            || Carbon::parse($record->last_used_at, 'UTC')->lessThan(now('UTC')->subMinutes(5))
            || $record->last_used_ip !== $ip) {
            DB::table('onec_orders_access_tokens')->where('id', $record->id)->update([
                'last_used_at' => now(),
                'last_used_ip' => $ip,
            ]);
        }

        return $record;
    }
}
