<?php

namespace App\Filament\Resources\PrivatePriceLinks;

use App\Models\Pricing\PrivatePriceLink;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PrivatePriceLinkResource extends Resource
{
    protected static ?string $model = PrivatePriceLink::class;
    protected static ?string $navigationLabel = 'Цены по закрытым ссылкам';
    protected static ?string $modelLabel = 'закрытая цена';
    protected static ?string $pluralModelLabel = 'Цены по закрытым ссылкам';
    protected static string|\UnitEnum|null $navigationGroup = 'Продажи';

    public static function canViewAny(): bool { return (bool) auth()->user()?->hasRole('super_admin'); }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return static::canViewAny(); }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return static::canViewAny(); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Название объявления')->required()->maxLength(255),
            Select::make('product_id')->label('Товар / торговое предложение')->relationship('product', 'name',
                modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('state', 'active')
                    ->where(fn ($query) => $query->whereNull('is_variable')->orWhere('is_variable', false)))
                ->searchable(['name', 'sku'])->required(),
            TextInput::make('price')->label('Цена по ссылке')->numeric()->minValue(0.01)->required()->prefix('₽'),
            Toggle::make('is_active')->label('Ссылка активна')->default(true),
            DateTimePicker::make('starts_at')->label('Начало действия'),
            DateTimePicker::make('expires_at')->label('Окончание действия')->default(now()->addDays(7))->required()->after('starts_at'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            \Filament\Tables\Columns\TextColumn::make('name')->label('Объявление')->searchable(),
            \Filament\Tables\Columns\TextColumn::make('product.sku')->label('SKU'),
            \Filament\Tables\Columns\TextColumn::make('price')->label('Цена')->money('RUB'),
            \Filament\Tables\Columns\IconColumn::make('is_active')->label('Активна')->boolean(),
            \Filament\Tables\Columns\TextColumn::make('expires_at')->label('До')->dateTime(),
        ])->recordActions([
            \Filament\Actions\EditAction::make(),
            \Filament\Actions\Action::make('link')->label('Выпустить новую ссылку')->requiresConfirmation()
                ->modalDescription('Предыдущая ссылка и доступ по ней перестанут действовать. Новую ссылку сохраните из уведомления.')
                ->action(function (PrivatePriceLink $record): void {
                    $url = url('/offer/'.$record->rotateToken());
                    \Filament\Notifications\Notification::make()->title('Новая ссылка')->body($url)->persistent()->success()->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPrivatePriceLinks::route('/'), 'create' => Pages\CreatePrivatePriceLink::route('/create'),
            'edit' => Pages\EditPrivatePriceLink::route('/{record}/edit')];
    }
}
