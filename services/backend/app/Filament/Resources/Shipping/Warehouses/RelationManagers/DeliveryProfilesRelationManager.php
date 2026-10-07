<?php

namespace App\Filament\Resources\Shipping\Warehouses\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliveryProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveryProfiles';

    protected static ?string $title = 'Зоны, цены и сроки';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Основные условия')->description('Один профиль описывает, куда склад доставляет и на каких условиях.')->schema([
                TextInput::make('name')->label('Название направления')->placeholder('Например, Липецкое направление')->required()->maxLength(255)->columnSpanFull(),
                Select::make('coverage_type')->label('Как определить зону')->options([
                    'locations' => 'Выбранные регионы и населённые пункты',
                    'radius' => 'Радиус от склада',
                    'hybrid' => 'Территории и радиус одновременно',
                ])->default('locations')->live()->required(),
                TextInput::make('radius_km')->label('Радиус')->numeric()->minValue(0)->suffix('км')->visible(fn ($get) => in_array($get('coverage_type'), ['radius', 'hybrid'], true)),
                Select::make('locations')->label('Куда доставляем')->relationship('locations', 'name')->multiple()->searchable()->preload()->visible(fn ($get) => in_array($get('coverage_type'), ['locations', 'hybrid'], true))->columnSpanFull(),
                TextInput::make('base_price')->label('Стоимость')->numeric()->minValue(0)->default(0)->prefix('₽')->required(),
                TextInput::make('delivery_days_min')->label('Срок от')->numeric()->minValue(0)->default(0)->suffix('дн.')->required(),
                TextInput::make('delivery_days_max')->label('Срок до')->numeric()->minValue(0)->gte('delivery_days_min')->default(0)->suffix('дн.')->required(),
                Toggle::make('is_active')->label('Профиль активен')->default(true),
            ])->columns(2),
            Section::make('Дополнительный расчёт')->collapsed()->schema([
                TextInput::make('price_per_km')->label('Доплата за километр')->numeric()->minValue(0)->default(0)->prefix('₽'),
                TextInput::make('free_delivery_threshold')->label('Бесплатно от суммы')->numeric()->minValue(0)->prefix('₽'),
                TextInput::make('priority')->label('Приоритет')->numeric()->minValue(0)->default(100)->required(),
            ])->columns(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Направление')->searchable(),
            TextColumn::make('locations.name')->label('Территории')->listWithLineBreaks()->limitList(3),
            TextColumn::make('base_price')->label('Цена')->money('RUB'),
            TextColumn::make('delivery_days_min')->label('Срок')->formatStateUsing(fn ($state, $record) => "{$state}–{$record->delivery_days_max} дн."),
            IconColumn::make('is_active')->label('Активен')->boolean(),
        ])->headerActions([
            CreateAction::make()->label('Добавить профиль доставки'),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make(),
        ])->defaultSort('priority');
    }
}
