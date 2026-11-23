<?php

namespace App\Services\Address;

use App\Models\Address\Address;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

class DefaultAddressManager
{
    /**
     * Serialize all default-address mutations for the same user, including
     * concurrent inserts when the user has no addresses to lock yet.
     */
    public function forUser(User $user, Closure $callback): mixed
    {
        return DB::transaction(function () use ($user, $callback): mixed {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            return $callback();
        }, 3);
    }

    /**
     * Maintain exactly one default when the user has any saved addresses.
     * Prefer an explicit choice; otherwise keep the current default, or
     * choose the oldest saved address with ID as a deterministic tie-breaker.
     *
     * Must be called inside forUser(), while holding the user-row lock.
     */
    public function ensure(User $user, ?Address $preferred = null): ?Address
    {
        if ($preferred !== null && (int) $preferred->user_id !== (int) $user->id) {
            throw new LogicException('Cannot assign an address belonging to another user.');
        }

        $chosen = $preferred
            ?? $user->addresses()->where('is_default', true)
                ->orderBy('created_at')->orderBy('id')->first()
            ?? $user->addresses()->orderBy('created_at')->orderBy('id')->first();

        if ($chosen === null) {
            return null;
        }

        $user->addresses()->where('id', '!=', $chosen->id)
            ->where('is_default', true)->update(['is_default' => false]);
        $user->addresses()->whereKey($chosen->id)
            ->where('is_default', false)->update(['is_default' => true]);

        return $chosen->fresh();
    }
}
