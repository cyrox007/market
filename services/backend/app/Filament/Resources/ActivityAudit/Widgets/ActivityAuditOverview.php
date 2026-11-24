<?php

namespace App\Filament\Resources\ActivityAudit\Widgets;

use App\Models\ActivityAuditEvent;
use App\Services\Audit\ActivityAudit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

final class ActivityAuditOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    public static function canView(): bool { return ActivityAudit::canView(auth()->user()); }

    protected function getStats(): array
    {
        abort_unless(static::canView(), 403);
        $data = Cache::remember('activity-audit-overview:v1', 60, function (): array {
            $day = ActivityAuditEvent::query()->where('created_at', '>=', now()->subDay());
            $chart = ActivityAuditEvent::query()->where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')->groupByRaw('DATE(created_at)')->pluck('total','day');
            return [
                'events' => (clone $day)->count(),
                'users' => (clone $day)->whereNotNull('actor_id')->distinct()->count('actor_id'),
                'errors' => (clone $day)->whereIn('result',['denied','error'])->count(),
                'chart' => array_map(fn ($offset) => (int) ($chart[now()->subDays($offset)->toDateString()] ?? 0), range(6,0)),
            ];
        });
        return [
            Stat::make('Событий за 24 часа', $data['events'])->chart($data['chart'])->description('График за 7 дней'),
            Stat::make('Аккаунтов за 24 часа', $data['users']),
            Stat::make('Отказы и ошибки за 24 часа', $data['errors'])->color($data['errors'] ? 'warning' : 'success'),
        ];
    }
}
