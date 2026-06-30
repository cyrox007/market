<?php

namespace App\Filament\Widgets;

use App\Models\Order\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class OrdersChartWidget extends ChartWidget
{
    protected static ?int $sort = 7;

    protected ?string $heading = 'Заказы и выручка за 14 дней';

    protected ?string $description = 'Динамика по дням';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeFinancial();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $cacheKey = 'dashboard_orders_chart_' . (auth()->id() ?? 'guest');
        $data = Cache::remember($cacheKey, 120, function () {
            $labels = [];
            $ordersCount = [];
            $revenue = [];
            for ($i = 13; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $labels[] = $date->translatedFormat('d.m');
                $ordersCount[] = Order::whereDate('created_at', $date)->count();
                $revenue[] = round((float) Order::whereDate('created_at', $date)
                    ->where('status', '!=', 'cancelled')
                    ->sum('total') / 1000, 1);
            }

            return [
                'labels' => $labels,
                'orders' => $ordersCount,
                'revenue' => $revenue,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Количество заказов',
                    'data' => $data['orders'],
                    'backgroundColor' => 'rgba(245, 158, 11, 0.5)',
                    'borderColor' => 'rgb(245, 158, 11)',
                ],
                [
                    'label' => 'Выручка (тыс. ₽)',
                    'data' => $data['revenue'],
                    'backgroundColor' => 'rgba(34, 197, 94, 0.5)',
                    'borderColor' => 'rgb(34, 197, 94)',
                ],
            ],
            'labels' => $data['labels'],
        ];
    }
}
