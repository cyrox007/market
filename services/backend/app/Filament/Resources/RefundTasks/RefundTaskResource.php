<?php
namespace App\Filament\Resources\RefundTasks;

class RefundTaskResource extends \Filament\Resources\Resource
{
    protected static ?string $model = \App\Models\Order\RefundTask::class;
    protected static ?string $navigationLabel = 'Возвраты после отмены';
    protected static ?string $pluralModelLabel = 'Возвраты после отмены';
    protected static string|\UnitEnum|null $navigationGroup = 'Заказы';
    public static function canViewAny(): bool { return (bool) auth()->user()?->can('viewAny', \App\Models\Order\Order::class); }
    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table->columns([
            \Filament\Tables\Columns\TextColumn::make('order.number')->label('Заказ'),
            \Filament\Tables\Columns\TextColumn::make('amount')->label('Осталось вернуть')->money('RUB'),
            \Filament\Tables\Columns\TextColumn::make('status')->label('Статус')->formatStateUsing(fn ($state) => $state === 'completed' ? 'Возврат подтверждён' : 'Требуется проверка и возврат'),
            \Filament\Tables\Columns\TextColumn::make('updated_at')->label('Обновлено')->dateTime(),
        ])->recordActions([
            \Filament\Actions\Action::make('order')->label('Открыть заказ')
                ->visible(fn ($record) => (bool) auth()->user()?->can('view', $record->order))
                ->url(fn ($record) => \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $record->order_id])),
        ])->defaultSort('updated_at', 'desc');
    }
    public static function getPages(): array { return ['index' => Pages\ListRefundTasks::route('/')]; }
}
