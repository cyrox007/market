<?php

namespace App\Filament\Widgets;

use App\Models\Order\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class OrdersChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Оформления за 14 дней';

    protected ?string $description = 'Количество созданных заказов, включая впоследствии отменённые. Без смешения рублей и штук.';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeOrders();
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $key = 'dashboard_order_trend_v2_'.today()->toDateString();
        return Cache::remember($key, 120, static function (): array {
            $start = today()->subDays(13);
            $counts = Order::query()->where('created_at', '>=', $start)
                ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
                ->groupByRaw('DATE(created_at)')
                ->pluck('total', 'day');

            $labels = [];
            $orders = [];
            for ($i = 0; $i < 14; $i++) {
                $day = $start->copy()->addDays($i);
                $labels[] = $day->format('d.m');
                $orders[] = (int) ($counts[$day->toDateString()] ?? 0);
            }

            return [
                'datasets' => [[
                    'label' => 'Создано заказов',
                    'data' => $orders,
                    'borderColor' => 'rgb(220, 38, 38)',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.08)',
                    'fill' => true,
                    'tension' => 0.25,
                ]],
                'labels' => $labels,
            ];
        });
    }
}
