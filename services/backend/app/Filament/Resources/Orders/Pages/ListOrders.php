<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        $counts = \App\Models\Order\Order::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $tab = \Filament\Schemas\Components\Tabs\Tab::class;
        return [
            'all' => $tab::make('Все')->badge($counts->sum()),
            'processing' => $tab::make('В обработке')->badge($counts['manager_processing'] ?? 0)
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'manager_processing')),
            'assembly' => $tab::make('Сборка и выдача')->badge(($counts['assembling'] ?? 0) + ($counts['assembled'] ?? 0))
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['assembling', 'assembled'])),
            'cancellations' => $tab::make('Просьбы отменить')->badge(\App\Models\Order\Order::whereHas('cancellationRequest', fn ($query) => $query->where('status', '!=', 'resolved'))->count())
                ->modifyQueryUsing(fn ($query) => $query->whereHas('cancellationRequest', fn ($q) => $q->where('status', '!=', 'resolved'))),
            'refunds' => $tab::make('Возвраты денег')->badge(\App\Models\Order\Order::whereHas('refundTask', fn ($query) => $query->where('status', '!=', 'completed'))->count())
                ->modifyQueryUsing(fn ($query) => $query->whereHas('refundTask', fn ($q) => $q->where('status', '!=', 'completed'))),
            'failed' => $tab::make('Ошибки передачи')->badge(\App\Models\Order\Order::where('integration_state', 'failed')->count())
                ->modifyQueryUsing(fn ($query) => $query->where('integration_state', 'failed')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/list_orders.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/list_orders.title');
    }

}
