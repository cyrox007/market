<?php

namespace App\Filament\Resources\Shipping\Warehouses\Schemas;

use App\Models\Inventory\Warehouse;
use App\Services\Address\AddressDirectoryClient;
use App\Services\Address\WarehouseAddressOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название склада')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('external_id')
                            ->label('Внешний ID склада (1С, stockId)')
                            ->maxLength(36)
                            ->unique(ignoreRecord: true)
                            ->helperText('Для склада из 1С. Для ручного источника будет создан автоматически.'),

                        Select::make('source_type')
                            ->label('Тип источника')
                            ->options([
                                'physical' => 'Собственный склад',
                                'manufacturer' => 'Фабрика',
                                'supplier' => 'Поставщик',
                            ])
                            ->default('physical')
                            ->live()
                            ->required(),

                        Select::make('manufacturer_id')
                            ->label('Производитель')
                            ->relationship('manufacturer', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => $get('source_type') === 'manufacturer'),

                        Select::make('stock_mode')
                            ->label('Как учитывать товары')
                            ->options([
                                'quantity' => 'По фактическим остаткам',
                                'availability' => 'Доступность под заказ',
                            ])
                            ->default('quantity')
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Адрес и подготовка заказа')
                    ->description('Физический склад: выберите общую площадку или задайте адрес ниже. Фабрика/поставщик: адрес необязателен, если точка отгрузки неизвестна.')
                    ->schema([
                        \App\Filament\Forms\PhysicalSiteSelect::make()->columnSpanFull()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('latitude', null);
                                $set('longitude', null);
                            }),
                        Select::make('directory_locality')
                            ->visible(fn (Get $get): bool => blank($get('physical_site_id')))
                            ->label('1. Населённый пункт')->searchable()->live()->dehydrated(false)
                            ->searchPrompt('Введите минимум две буквы города, посёлка или села')
                            ->getSearchResultsUsing(fn (string $search): array => app(WarehouseAddressOptions::class)->search('localities', null, $search))
                            ->getOptionLabelUsing(fn ($value): ?string => $value ? app(AddressDirectoryClient::class)->cachedLabel($value) ?? $value : null)
                            ->afterStateUpdated(function (Set $set): void {
                                foreach (['directory_street', 'address_external_id', 'address', 'gar_guid', 'kladr_code', 'latitude', 'longitude'] as $field) {
                                    $set($field, null);
                                }
                            })->helperText('В результатах указан регион — выберите нужный одноимённый населённый пункт.')
                            ->columnSpanFull(),
                        Select::make('directory_street')
                            ->visible(fn (Get $get): bool => blank($get('physical_site_id')))
                            ->label('2. Улица')->searchable()->live()->dehydrated(false)
                            ->disabled(fn (Get $get): bool => blank($get('directory_locality')))
                            ->getSearchResultsUsing(fn (string $search, Get $get): array => app(WarehouseAddressOptions::class)->search('streets', $get('directory_locality'), $search))
                            ->getOptionLabelUsing(fn ($value): ?string => $value ? app(AddressDirectoryClient::class)->cachedLabel($value) ?? $value : null)
                            ->afterStateUpdated(function (Set $set): void {
                                foreach (['address_external_id', 'address', 'gar_guid', 'kladr_code', 'latitude', 'longitude'] as $field) {
                                    $set($field, null);
                                }
                            })->helperText('Если у здания нет улицы, оставьте пустым и ищите дом прямо в населённом пункте.')
                            ->columnSpanFull(),
                        Select::make('address_external_id')
                            ->visible(fn (Get $get): bool => blank($get('physical_site_id')))
                            ->label('3. Дом / корпус / строение')
                            ->required(fn ($get) => $get('source_type') === 'physical' && blank($get('physical_site_id')))
                            ->searchable()
                            ->live()
                            ->searchPrompt('Введите номер дома')
                            ->disabled(fn (Get $get): bool => blank($get('directory_locality')))
                            ->searchingMessage('Ищем адрес…')
                            ->noSearchResultsMessage('Здания не найдены. Проверьте населённый пункт, улицу и номер.')
                            ->getSearchResultsUsing(fn (string $search, Get $get): array => app(WarehouseAddressOptions::class)->search(
                                'buildings', $get('directory_street') ?: $get('directory_locality'), $search))
                            ->getOptionLabelUsing(function ($value, ?Warehouse $record): ?string {
                                if (blank($value)) {
                                    return null;
                                }

                                return (($record?->address_snapshot['selectionId'] ?? $record?->address_external_id) === (string) $value ? $record?->address : null)
                                    ?? app(AddressDirectoryClient::class)->cachedLabel((string) $value)
                                    ?? (string) $value;
                            })
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $set('latitude', null);
                                $set('longitude', null);
                                $set('gar_guid', $state && preg_match('/^[0-9a-f-]{36}$/i', $state) ? $state : null);
                                $set('kladr_code', null);
                                if (blank($state)) {
                                    $set('address', null);
                                    $set('gar_guid', null);
                                    $set('kladr_code', null);

                                    return;
                                }

                                $label = app(AddressDirectoryClient::class)->cachedLabel((string) $state);

                                if ($label !== null) {
                                    $set('address', $label);
                                }
                            })
                            ->helperText('При сохранении сервер проверит здание по справочнику. ГАР GUID и КЛАДР-код сохраняются отдельно, если доступны.')
                            ->columnSpanFull(),

                        TextInput::make('address')
                            ->label('Полный адрес')
                            ->readOnly()
                            ->dehydrated(false)
                            ->helperText('Заполняется из справочника. Произвольный текст вместо здания не сохраняется.')
                            ->columnSpanFull(),
                        TextInput::make('gar_guid')->label('GUID здания ГАР')->readOnly()->dehydrated(false),
                        TextInput::make('kladr_code')->label('Код КЛАДР')->readOnly()->dehydrated(false)
                            ->helperText('Код совместимости. У отдельных зданий может отсутствовать.'),
                        TextInput::make('latitude')->label('Широта отдельного въезда склада')->numeric()->minValue(-90)->maxValue(90)
                            ->requiredWith('longitude')->helperText('Оставьте обе пустыми для использования координат площадки. Укажите, если въезд/отгрузка в другой точке.'),
                        TextInput::make('longitude')->label('Долгота склада')->numeric()->minValue(-180)->maxValue(180)
                            ->requiredWith('latitude'),
                        TextInput::make('processing_days_min')->label('Подготовка от')->numeric()->minValue(0)->default(0)->suffix('дн.'),
                        TextInput::make('processing_days_max')->label('Подготовка до')->numeric()->minValue(0)->gte('processing_days_min')->default(0)->suffix('дн.'),
                    ])->columns(2),

                Section::make('Старая привязка к локациям')
                    ->description('Оставлена временно для совместимости. Новые условия создавайте после сохранения склада во вкладке «Зоны, цены и сроки».')
                    ->collapsed()
                    ->schema([
                        Select::make('shippingLocations')
                            ->label('Локации')
                            ->relationship('shippingLocations', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Если склад привязан к родительской локации, он доступен и в дочерних локациях.'),
                    ]),

                Section::make('Дополнительные данные')
                    ->schema([
                        Textarea::make('meta')
                            ->label('Meta (JSON)')
                            ->rows(5)
                            ->helperText('Опционально: JSON с произвольными данными склада.')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $state)
                            ->dehydrateStateUsing(function ($state) {
                                if ($state === null || $state === '') {
                                    return null;
                                }
                                if (is_array($state)) {
                                    return $state;
                                }
                                $decoded = json_decode((string) $state, true);

                                return is_array($decoded) ? $decoded : null;
                            }),
                    ]),
            ]);
    }
}
