<?php

namespace App\Filament\Widgets;

use App\Models\Order\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

class FinancialOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Финансы';

    protected ?string $description = 'Выручка и средний чек';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeFinancial();
    }

    protected function getStats(): array
    {
        $cacheKey = 'dashboard_financial_stats_' . (auth()->id() ?? 'guest');
        $stats = Cache::remember($cacheKey, 120, function () {
            $query = Order::where('status', '!=', 'cancelled');

            $revenueMonth = (float) (clone $query)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total');
            $revenueToday = (float) (clone $query)
                ->whereDate('created_at', today())
                ->sum('total');
            $ordersCountMonth = (clone $query)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
            $avgCheck = $ordersCountMonth > 0
                ? (float) (clone $query)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->avg('total')
                : 0;

            return [
                'revenue_month' => $revenueMonth,
                'revenue_today' => $revenueToday,
                'avg_check' => round($avgCheck, 2),
            ];
        });

        $format = fn($v) => number_format($v, 0, ',', ' ') . ' ₽';

        return [
            Stat::make('Выручка за месяц', $format($stats['revenue_month']))
                ->description('Текущий месяц, без отменённых')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color('success')
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Выручка сегодня', $format($stats['revenue_today']))
                ->description('За текущий день')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color('primary')
                ->icon(Heroicon::OutlinedCurrencyDollar),
            Stat::make('Средний чек (месяц)', $format($stats['avg_check']))
                ->description('Средняя сумма заказа')
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->color('gray')
                ->icon(Heroicon::OutlinedCalculator),
        ];
    }
}
