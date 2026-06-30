<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

class UsersStatsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Пользователи';

    protected ?string $description = 'Регистрации и активность';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeUsers();
    }

    protected function getStats(): array
    {
        $cacheKey = 'dashboard_users_stats_' . (auth()->id() ?? 'guest');
        $stats = Cache::remember($cacheKey, 120, function () {
            $total = User::count();
            $thisMonth = User::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            return [
                'total' => $total,
                'this_month' => $thisMonth,
            ];
        });

        return [
            Stat::make('Всего пользователей', number_format($stats['total'], 0, ',', ' '))
                ->description('Зарегистрировано в системе')
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('gray')
                ->icon(Heroicon::OutlinedUserGroup),
            Stat::make('Новых за месяц', number_format($stats['this_month'], 0, ',', ' '))
                ->description(now()->translatedFormat('F Y'))
                ->descriptionIcon(Heroicon::OutlinedUserPlus)
                ->color('success')
                ->icon(Heroicon::OutlinedSparkles),
        ];
    }
}
