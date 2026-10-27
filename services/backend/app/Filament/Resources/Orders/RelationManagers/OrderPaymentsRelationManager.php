<?php
namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Payment history only; all changes use the guarded actions of the order workspace. */
class OrderPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $title = 'История оплаты';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label('Дата')->dateTime('d.m.Y H:i'),
            TextColumn::make('method.name')->label('Способ оплаты'),
            TextColumn::make('status')->label('Результат')->formatStateUsing(fn ($record) => match ($record->getRawOriginal('status')) {
                'paid' => 'Оплачено', 'pending' => 'Ожидается оплата', 'cancelled' => 'Отменено',
                'refunded' => 'Возвращено', 'partially_refunded' => 'Возвращено частично',
                'declined' => 'Банк отклонил', 'timeout' => 'Время оплаты истекло', default => 'Проверяется',
            })->badge(),
            TextColumn::make('amount')->label('К оплате')->money('RUB'),
            TextColumn::make('amount_paid')->label('Получено')->money('RUB'),
        ])->defaultSort('created_at', 'desc')->headerActions([])->recordActions([])->toolbarActions([]);
    }
}