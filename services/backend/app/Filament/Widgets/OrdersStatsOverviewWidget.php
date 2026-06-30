<?php

namespace App\Filament\Widgets;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

class OrdersStatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Заказы';

    protected ?string $description = 'Сводка по заказам';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeOrders();
    }

    protected function getStats(): array
    {
        $cacheKey = 'dashboard_orders_stats_' . (auth()->id() ?? 'guest');
        $stats = Cache::remember($cacheKey, 120, function () {
            $total = Order::count();
            $thisMonth = Order::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
            $newToday = Order::whereDate('created_at', today())
                ->where('status', OrderStatus::NEW ->value)
                ->count();
            $inProgress = Order::whereIn('status', [
                OrderStatus::ACCEPTED->value,
                OrderStatus::ASSEMBLED->value,
                OrderStatus::SHIPPED->value,
                OrderStatus::IN_TRANSIT->value,
            ])->count();

            $chartData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $chartData[] = Order::whereDate('created_at', $date)->count();
            }

            return [
                'total' => $total,
                'this_month' => $thisMonth,
                'new_today' => $newToday,
                'in_progress' => $inProgress,
                'chart' => $chartData,
            ];
        });

        return [
            Stat::make('Всего заказов', number_format($stats['total'], 0, ',', ' '))
                ->description('За месяц: ' . number_format($stats['this_month'], 0, ',', ' '))
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->icon(Heroicon::OutlinedClipboardDocumentList),
            Stat::make('Новые сегодня', number_format($stats['new_today'], 0, ',', ' '))
                ->description('Требуют обработки')
                ->descriptionIcon(Heroicon::OutlinedBellAlert)
                ->color($stats['new_today'] > 0 ? 'warning' : 'success')
                ->chart($stats['chart'])
                ->icon(Heroicon::OutlinedSparkles),
            Stat::make('В работе', number_format($stats['in_progress'], 0, ',', ' '))
                ->description('Приняты, собираются или в пути')
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->color('info')
                ->icon(Heroicon::OutlinedCog6Tooth),
        ];
    }
}
