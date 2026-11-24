<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestOrdersTableWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Требуют внимания';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeOrders();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl(
                OrderResource::canEdit($record) ? 'edit' : 'view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')->label('Заказ')->weight('bold')
                    ->description(fn (Order $record) => $record->created_at?->format('d.m.Y H:i')),
                TextColumn::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn ($state) => OrderStatus::tryFrom($state)?->label() ?? $state)
                    ->color(fn ($state): string => match ($state) {
                        'new', 'manager_processing' => 'warning',
                        'assembling', 'assembled' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('attention_reason')->label('Причина')->state(static function (Order $record): string {
                    if ($record->integration_state === 'failed') {
                        return 'Ошибка передачи в интеграцию';
                    }
                    if ($record->cancellationRequest?->status !== null
                        && $record->cancellationRequest->status !== 'resolved') {
                        return 'Обращение на отмену';
                    }
                    if ($record->refundTask?->status !== null
                        && $record->refundTask->status !== 'completed') {
                        return 'Ожидается возврат';
                    }

                    return match ($record->status) {
                        'new', 'manager_processing' => 'Ожидает обработки',
                        'assembling' => 'Сборка',
                        'assembled' => 'Ожидает отгрузки',
                        default => 'Проверить заказ',
                    };
                })->wrap(),
                TextColumn::make('total')->label('Сумма')->money('RUB'),
            ])
            ->emptyStateHeading('Нет заказов, требующих внимания')
            ->emptyStateDescription('Новые задачи и проблемные операции появятся здесь.')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10]);
    }

    protected function getTableQuery(): Builder
    {
        return Order::query()
            ->with(['cancellationRequest', 'refundTask'])
            ->where(static function (Builder $query): void {
                $query->whereIn('status', [
                    OrderStatus::NEW->value,
                    OrderStatus::MANAGER_PROCESSING->value,
                    OrderStatus::ASSEMBLING->value,
                    OrderStatus::ASSEMBLED->value,
                ])->orWhere('integration_state', 'failed')
                    ->orWhereHas('cancellationRequest', fn (Builder $sub) => $sub->where('status', '!=', 'resolved'))
                    ->orWhereHas('refundTask', fn (Builder $sub) => $sub->where('status', '!=', 'completed'));
            })
            ->orderByRaw("CASE status WHEN 'manager_processing' THEN 0 WHEN 'new' THEN 1 WHEN 'assembling' THEN 2 WHEN 'assembled' THEN 3 ELSE 4 END")
            ->orderBy('created_at', 'asc');
    }
}
