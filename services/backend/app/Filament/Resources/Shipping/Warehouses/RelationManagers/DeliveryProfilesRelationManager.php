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
        return $schema->columns(1)->components([
            Section::make('Основные условия')->description('Один профиль описывает, куда склад доставляет и на каких условиях.')->schema([
                TextInput::make('name')->label('Название направления')->placeholder('Например, Липецкое направление')->required()->maxLength(255)->columnSpanFull(),
                Select::make('coverage_type')->label('Как определить зону')->options([
                    'locations' => 'Выбранные регионы и населённые пункты',
                    'radius' => 'Радиус от склада',
                    'hybrid' => 'Территории и радиус одновременно',
                ])->default('locations')->live()->required()->columnSpanFull(),
                TextInput::make('radius_km')->label('Радиус по прямой')->numeric()->minValue(0.01)->suffix('км')
                    ->required(fn ($get) => in_array($get('coverage_type'), ['radius', 'hybrid'], true))
                    ->visible(fn ($get) => in_array($get('coverage_type'), ['radius', 'hybrid'], true)),
                \Filament\Forms\Components\Repeater::make('directory_territories')->label('Покрытие по ГАР / КЛАДР')
                    ->required(fn ($get) => in_array($get('coverage_type'), ['locations', 'hybrid'], true))
                    ->minItems(1)
                    ->helperText('Выберите регион целиком или уточните населённый пункт. Все дочерние адреса входят в покрытие. Можно добавить несколько территорий.')
                    ->schema([
                        \App\Filament\Forms\DirectorySelectLoading::apply(Select::make('region_id'), 'регионы')->label('1. Регион')->searchable()->live()->required()
                            ->options(fn (): array => collect(app(\App\Services\Address\AddressDirectoryClient::class)->options('regions', ['limit' => 100]))
                                ->mapWithKeys(fn (array $item): array => [$item['externalId'] => $item['label'] ?? $item['name']])->all())
                            ->getSearchResultsUsing(function (string $search): array {
                                $items = app(\App\Services\Address\AddressDirectoryClient::class)->options('regions', ['q' => $search, 'limit' => 50]);

                                return collect($items)->mapWithKeys(fn (array $item) => [$item['externalId'] => $item['label'] ?? $item['name']])->all();
                            })
                            ->getOptionLabelUsing(fn ($value) => $value ? (app(\App\Services\Address\AddressDirectoryClient::class)->hierarchy($value)['label'] ?? $value) : null)
                            ->afterStateUpdated(fn ($set) => $set('locality_id', null)),
                        \App\Filament\Forms\DirectorySelectLoading::apply(Select::make('locality_id'), 'населённые пункты', ['region_id'])->label('2. Населённый пункт (необязательно)')->searchable()
                            ->options(fn ($get): array => filled($get('region_id'))
                                ? app(\App\Services\Address\WarehouseAddressOptions::class)->search('localities', $get('region_id'), '') : [])
                            ->helperText('Показаны первые 50 вариантов региона. Для остальных введите название.')
                            ->placeholder('Весь выбранный регион')->disabled(fn ($get) => blank($get('region_id')))
                            ->getSearchResultsUsing(fn (string $search, $get): array => app(\App\Services\Address\WarehouseAddressOptions::class)->search('localities', $get('region_id'), $search))
                            ->getOptionLabelUsing(fn ($value) => $value ? (app(\App\Services\Address\AddressDirectoryClient::class)->hierarchy($value)['label'] ?? $value) : null),
                    ])->columns(2)->defaultItems(0)->addActionLabel('Добавить территорию')
                    ->visible(fn ($get) => in_array($get('coverage_type'), ['locations', 'hybrid'], true))->columnSpanFull(),
                \Filament\Schemas\Components\Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('base_price')->label('Стоимость доставки')->numeric()->minValue(0)->default(0)->prefix('₽')->required(),
                    TextInput::make('delivery_days_min')->label('Срок от')->numeric()->minValue(0)->default(0)->suffix('дн.')->required(),
                    TextInput::make('delivery_days_max')->label('Срок до')->numeric()->minValue(0)->gte('delivery_days_min')->default(0)->suffix('дн.')->required(),
                ])->columnSpanFull(),
                Toggle::make('is_active')->label('Профиль активен')->default(true)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('Дополнительный расчёт')->collapsed()->schema([
                TextInput::make('price_per_km')->label('Доплата за километр по прямой')->numeric()->minValue(0)->default(0)->prefix('₽')
                    ->helperText('Не дорожный маршрут. При отсутствии координат этот тариф недоступен.'),
                TextInput::make('free_delivery_threshold')->label('Бесплатно от суммы')->numeric()->minValue(0)->prefix('₽'),
                TextInput::make('priority')->label('Приоритет')->numeric()->minValue(0)->default(100)->required(),
            ])->columns(['default' => 1, 'md' => 3])->columnSpanFull(),
            Section::make('Старые локации')->collapsed()->schema([
                Select::make('locations')->label('Прежние привязки')->relationship('locations', 'name')->multiple()->searchable()->preload()
                    ->helperText('Для старого расчёта. Новый расчёт по адресу использует покрытие ГАР / КЛАДР выше.'),
            ])->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Направление')->searchable(),
            TextColumn::make('coverage_summary')->label('Территории ГАР / КЛАДР')
                ->state(fn ($record): string => $record->coverage_type === 'radius' ? "Радиус {$record->radius_km} км" : collect($record->directory_territories ?? [])->pluck('label')->implode('; ')),
            TextColumn::make('base_price')->label('Цена')->money('RUB'),
            TextColumn::make('delivery_days_min')->label('Срок')->formatStateUsing(fn ($state, $record) => "{$state}–{$record->delivery_days_max} дн."),
            IconColumn::make('is_active')->label('Активен')->boolean(),
        ])->emptyStateHeading('Условия доставки пока не настроены')
            ->emptyStateDescription('Добавьте направление, стоимость и срок доставки.')
            ->headerActions([
                CreateAction::make()->label('Добавить профиль доставки')
                    ->modalHeading('Новый профиль доставки')->modalWidth(\Filament\Support\Enums\Width::FourExtraLarge)
                    ->createAnother(false),
            ])->recordActions([
                EditAction::make()->modalHeading('Редактирование профиля доставки')->modalWidth(\Filament\Support\Enums\Width::FourExtraLarge),
                DeleteAction::make(),
            ])->defaultSort('priority');
    }
}
