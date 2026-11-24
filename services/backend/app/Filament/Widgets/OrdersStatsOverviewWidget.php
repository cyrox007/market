<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class OrdersStatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Очереди заказов';

    protected ?string $description = 'Текущие задачи, не только созданные сегодня';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeOrders();
    }

    protected function getStats(): array
    {
        $counts = Cache::remember('dashboard_order_queues_v2', 90, fn (): array => Order::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all());

        $card = static function (string $label, OrderStatus $status, string $color, Heroicon $icon) use ($counts): Stat {
            $count = $counts[$status->value] ?? 0;

            return Stat::make($label, number_format($count, 0, ',', ' '))
                ->description($count > 0 ? 'Открыть заказы' : 'Нет ожидающих заказов')
                ->color($count > 0 ? $color : 'gray')
                ->icon($icon)
                ->url(OrderResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => $status->value]],
                ]));
        };

        return [
            $card('Новые', OrderStatus::NEW, 'info', Heroicon::OutlinedInbox),
            $card('На проверке менеджера', OrderStatus::MANAGER_PROCESSING, 'warning', Heroicon::OutlinedClipboardDocumentCheck),
            $card('На сборке', OrderStatus::ASSEMBLING, 'info', Heroicon::OutlinedCube),
            $card('Готовы к отгрузке', OrderStatus::ASSEMBLED, 'success', Heroicon::OutlinedTruck),
        ];
    }
}
