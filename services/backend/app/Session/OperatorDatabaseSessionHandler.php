<?php

namespace App\Session;

use App\Models\User;
use Illuminate\Session\DatabaseSessionHandler;

class OperatorDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected function expired($session): bool
    {
        if (! parent::expired($session)) {
            return false;
        }

        $minutes = max($this->minutes, (int) config('session.operator_lifetime', 720));
        if (! isset($session->user_id, $session->last_activity)
            || $session->last_activity < now()->subMinutes($minutes)->getTimestamp()) {
            return true;
        }

        return ! User::find($session->user_id)?->hasAnyRole(['super_admin', 'admin', 'manager', 'editor', 'viewer']);
    }

    public function gc($lifetime): int
    {
        // Public sessions still expire on read at their original TTL. Keep rows long
        // enough that a public request's lottery cannot delete a sleeping operator.
        return parent::gc(max($lifetime, (int) config('session.operator_lifetime', 720) * 60));
    }
}
