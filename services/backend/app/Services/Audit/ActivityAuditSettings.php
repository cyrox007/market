<?php

namespace App\Services\Audit;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class ActivityAuditSettings
{
    private const KEY = 'activity_audit_settings:v1';

    public function get(): array
    {
        return Cache::remember(self::KEY, 30, function (): array {
            $row = DB::table('activity_audit_settings')->find(1);
            return [
                'behavior' => $row ? (bool) $row->behavior : (bool) config('activity_audit.behavior'),
                'retention_days' => $row ? array_replace(config('activity_audit.retention_days'), json_decode($row->retention_days, true, 512, JSON_THROW_ON_ERROR)) : config('activity_audit.retention_days'),
            ];
        });
    }

    public function save(User $actor, array $input): void
    {
        abort_unless($actor->hasRole('super_admin') && config('activity_audit.enabled'), 403);
        $rules = ['behavior' => 'required|boolean', 'retention_days' => 'required|array:security,admin,order,account,shopping,behavior'];
        foreach (config('activity_audit.retention_days') as $category => $days) {
            $min = in_array($category, ['security','admin','order'], true) ? 90 : 7;
            $rules['retention_days.'.$category] = 'required|integer|min:'.$min.'|max:1095';
        }
        $data = Validator::make($input, $rules)->validate();
        $days = array_map('intval', $data['retention_days']);
        DB::transaction(function () use ($actor, $data, $days): void {
            DB::table('activity_audit_settings')->upsert([[
                'id' => 1, 'behavior' => (bool) $data['behavior'],
                'retention_days' => json_encode($days, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]], ['id'], ['behavior','retention_days','updated_at']);
            $audit = app(ActivityAudit::class);
            $audit->record('security', 'audit.settings_changed', details: ['retention_days' => $days, 'behavior_enabled' => (bool) $data['behavior']], context: $audit->context($actor));
        });
        Cache::forget(self::KEY);
    }
}
