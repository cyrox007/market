<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order\Order;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class FinancialOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Стоимость заказов (не оплаченная выручка)';

    protected ?string $description = 'Неотменённые заказы по дате создания. Оплаты и возвраты учитываются отдельно в платёжном контуре.';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeFinancial();
    }

    protected function getStats(): array
    {
        $stats = Cache::remember('dashboard_order_amounts_v2_'.today()->toDateString(), 120, static function (): array {
            $orders = Order::query()->where('status', '!=', 'cancelled');
            $month = (clone $orders)->where('created_at', '>=', now()->startOfMonth());
            $today = (clone $orders)->where('created_at', '>=', today());

            return [
                'month_total' => (float) (clone $month)->sum('total'),
                'today_total' => (float) $today->sum('total'),
                'month_average' => (float) $month->avg('total'),
            ];
        });
        $format = static fn (float $value): string => number_format($value, 0, ',', ' ').' ₽';
        $url = OrderResource::getUrl('index');

        return [
            Stat::make('Сумма заказов за месяц', $format($stats['month_total']))
                ->description('Без отменённых; не равно оплатам')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->url($url),
            Stat::make('Сумма заказов сегодня', $format($stats['today_total']))
                ->description('По дате создания заказа')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('primary')
                ->url($url),
            Stat::make('Средняя сумма заказа за месяц', $format($stats['month_average']))
                ->description('Без отменённых; не является средним платежом')
                ->icon(Heroicon::OutlinedCalculator)
                ->color('gray')
                ->url($url),
        ];
    }
}
