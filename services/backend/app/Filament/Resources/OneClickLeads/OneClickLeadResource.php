<?php

namespace App\Filament\Resources\OneClickLeads;

use App\Models\Order\OneClickLead;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class OneClickLeadResource extends Resource
{
    protected static ?string $model = OneClickLead::class;

    protected static ?string $policy = \App\Policies\OneClickLeadPolicy::class;

    protected static ?string $modelLabel = 'заявка в один клик';

    protected static ?string $pluralModelLabel = 'Заявки в один клик';

    protected static string|UnitEnum|null $navigationGroup = 'Продажи';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-phone';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('number')->label('Номер заявки')->disabled(),
            TextInput::make('name')->label('Покупатель')->disabled(),
            TextInput::make('phone')->label('Телефон')->disabled(),
            TextInput::make('product_snapshot.name')->label('Товар / вариант')->disabled(),
            TextInput::make('product_snapshot.sku')->label('Артикул SKU')->disabled(),
            TextInput::make('quantity')->label('Количество')->disabled(),
            Textarea::make('comment')->label('Комментарий покупателя')->disabled(),
            TextInput::make('consents.accepted_at')->label('Время согласия с офертой и обработкой данных')->disabled(),
            Select::make('status')->label('Обработка менеджером')->options(['new' => 'Новая', 'contacted' => 'Связались с покупателем', 'completed' => 'Обработана', 'cancelled' => 'Отменена'])->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->label('Номер')->searchable(), TextColumn::make('name')->label('Покупатель')->searchable(),
            TextColumn::make('phone')->label('Телефон')->searchable(), TextColumn::make('product_snapshot.name')->label('Товар'),
            TextColumn::make('quantity')->label('Количество'), TextColumn::make('status')->label('Статус')->badge()
                ->formatStateUsing(fn ($state) => ['new' => 'Новая', 'contacted' => 'Связались', 'completed' => 'Обработана', 'cancelled' => 'Отменена'][$state] ?? $state),
            TextColumn::make('created_at')->label('Получена')->dateTime('d.m.Y H:i')->sortable(),
        ])->defaultSort('created_at', 'desc')->recordActions([EditAction::make()]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) OneClickLead::where('status', 'new')->count();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOneClickLeads::route('/'), 'edit' => Pages\EditOneClickLead::route('/{record}/edit')];
    }
}
