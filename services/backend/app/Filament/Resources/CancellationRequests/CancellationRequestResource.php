<?php
namespace App\Filament\Resources\CancellationRequests;

class CancellationRequestResource extends \Filament\Resources\Resource
{
    protected static ?string $model = \App\Models\Order\CancellationRequest::class;
    protected static ?string $navigationLabel = 'Заявки на отмену';
    protected static ?string $pluralModelLabel = 'Заявки на отмену';
    protected static string|\UnitEnum|null $navigationGroup = 'Заказы';

    protected static ?int $navigationSort = 20;
    public static function canViewAny(): bool { return (bool) auth()->user()?->can('viewAny', \App\Models\Order\Order::class); }
    public static function canCreate(): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return $record->order && (bool) auth()->user()?->can('update', $record->order); }
    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([
            \Filament\Forms\Components\TextInput::make('order_id')->label('Заказ')->disabled(),
            \Filament\Forms\Components\Textarea::make('reason')->label('Причина покупателя')->disabled(),
            \Filament\Forms\Components\Select::make('status')->label('Обработка')->options(['new' => 'Новая', 'review' => 'На рассмотрении', 'resolved' => 'Рассмотрена'])->required(),
            \Filament\Forms\Components\Textarea::make('resolution')->label('Решение менеджера')->maxLength(4000)
                ->helperText('Запись решения не отменяет заказ и не возвращает деньги. Отмена выполняется в карточке заказа уполномоченным сотрудником.'),
        ]);
    }
    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table->columns([
            \Filament\Tables\Columns\TextColumn::make('order.number')->label('Заказ')
                ->url(fn ($record) => \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $record->order_id])),
            \Filament\Tables\Columns\TextColumn::make('reason')->label('Причина')->limit(80),
            \Filament\Tables\Columns\TextColumn::make('status')->label('Обработка')->badge()
                ->formatStateUsing(fn ($state) => match ($state) { 'new' => 'Новое обращение', 'review' => 'На рассмотрении', 'resolved' => 'Рассмотрено', default => 'Требуется проверка' }),
            \Filament\Tables\Columns\TextColumn::make('created_at')->label('Дата')->dateTime(),
        ])->recordActions([\Filament\Actions\EditAction::make()->label('Рассмотреть')]);
    }
    public static function getPages(): array { return ['index' => Pages\ListCancellationRequests::route('/')]; }
}
