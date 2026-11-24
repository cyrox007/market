<?php

namespace App\Services\Audit;

use Illuminate\Database\Eloquent\Builder;

final class ActivityAuditHistory
{
    public static function forUser(Builder $query, int $id): Builder
    {
        // Link only guest activity preceding a login of this account. Exclude
        // shared session tags associated with multiple authenticated accounts.
        return $query->where(function (Builder $q) use ($id): void {
            $q->where('actor_id', $id)->orWhere(function (Builder $guest) use ($id): void {
                $guest->where('actor_type', 'guest')->whereNotNull('session_tag')
                    ->whereExists(function ($login) use ($id): void {
                        $login->selectRaw('1')->from('activity_audit_events as login_event')
                            ->whereColumn('login_event.session_tag', 'activity_audit_events.session_tag')
                            ->where('login_event.actor_id', $id)
                            ->whereIn('login_event.event_type', ['auth.login','auth.register'])
                            ->where('login_event.result', 'success')
                            ->whereColumn('login_event.id', '>', 'activity_audit_events.id');
                    })->whereNotExists(function ($other) use ($id): void {
                        $other->selectRaw('1')->from('activity_audit_events as other_event')
                            ->whereColumn('other_event.session_tag', 'activity_audit_events.session_tag')
                            ->whereNotNull('other_event.actor_id')->where('other_event.actor_id', '!=', $id);
                    });
            });
        });
    }
}
