<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestOrdersTableWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Последние заказы';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeOrders();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn(Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')
                    ->label('Номер')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'new' => 'info',
                        'accepted' => 'warning',
                        'assembled' => 'warning',
                        'shipped' => 'info',
                        'in_transit' => 'info',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'new' => 'Новый',
                        'accepted' => 'Принят',
                        'assembled' => 'Собран',
                        'shipped' => 'Отправлен',
                        'in_transit' => 'В пути',
                        'delivered' => 'Доставлен',
                        'cancelled' => 'Отменен',
                        default => $state,
                    }),
                TextColumn::make('total')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable(),
            ])
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10]);
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|null
    {
        return Order::query()->latest();
    }
}
