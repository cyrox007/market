<?php
namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\Order\OrderStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderStatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistory';
    protected static ?string $title = 'История заказа';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label('Когда')->dateTime('d.m.Y H:i'),
            TextColumn::make('status')->label('Событие')->formatStateUsing(fn ($state) => OrderStatus::tryFrom($state)?->label() ?? $state)->badge(),
            TextColumn::make('user.name')->label('Кто')->placeholder('Система'),
            TextColumn::make('comment')->label('Комментарий')->wrap()->placeholder('—'),
        ])->defaultSort('created_at', 'desc')->paginated(false)->headerActions([])->recordActions([])->toolbarActions([]);
    }
}
