<?php

namespace App\Filament\Resources\Shipping\Warehouses\Schemas;

use App\Filament\Resources\Shipping\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Shipping\Warehouses\RelationManagers\DeliveryProfilesRelationManager;
use App\Models\Inventory\Warehouse;
use App\Services\Address\AddressDirectoryClient;
use App\Services\Address\WarehouseAddressOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('warehouse')->columnSpanFull()->tabs([
                    Tab::make('Основное')
                        ->schema([
                            TextInput::make('name')
                                ->label('Название склада')
                                ->required()
                                ->maxLength(255)
                                ->helperText('Обязательно. Название источника товаров для сотрудников и вариантов доставки.'),

                            TextInput::make('external_id')
                                ->label('Внешний ID склада (1С, stockId)')
                                ->maxLength(36)
                                ->unique(ignoreRecord: true)
                                ->helperText('Необязательно для ручного создания: ID появится автоматически. Для склада из 1С используйте его stockId; изменение нарушит сопоставление остатков.'),

                            Select::make('source_type')
                                ->label('Тип источника')
                                ->options([
                                    'physical' => 'Собственный склад',
                                    'manufacturer' => 'Фабрика',
                                    'supplier' => 'Поставщик',
                                ])
                                ->default('physical')
                                ->live()
                                ->required()
                                ->helperText('Обязательно. Собственный склад хранит товары; фабрика или поставщик могут обеспечивать товары под заказ.'),

                            Select::make('manufacturer_id')
                                ->label('Производитель')
                                ->relationship('manufacturer', 'name')
                                ->searchable()
                                ->preload()
                                ->visible(fn ($get) => $get('source_type') === 'manufacturer')
                                ->helperText('Необязательно. Связывает источник с производителем каталога; само по себе не создаёт остатки и условия доставки.'),

                            Select::make('stock_mode')
                                ->label('Как учитывать товары')
                                ->options([
                                    'quantity' => 'По фактическим остаткам',
                                    'availability' => 'Доступность под заказ',
                                ])
                                ->default('quantity')
                                ->required()
                                ->helperText('Обязательно. Фактические остатки — количество товара на складе. Под заказ — признак доступности и срок подготовки у источника.'),

                            Toggle::make('is_active')
                                ->label('Активен')
                                ->default(true)
                                ->helperText('Выключите, чтобы исключить источник из новых расчётов. Существующие заказы не удаляются.'),
                            Toggle::make('pickup_enabled')->label('Разрешить самовывоз со склада')->default(false)
                                ->helperText('Необязательно, по умолчанию выключено. Разрешает выдачу с физического склада с фактическими остатками. Самовывоз через связанный магазин настраивается отдельно.'),
                        ])
                        ->columns(2),

                    Tab::make('Адрес')
                        ->columnSpanFull()
                        ->schema([
                            Section::make('Использовать адрес существующего магазина или склада')
                                ->collapsed()->schema([
                                    \App\Filament\Forms\PhysicalSiteSelect::make()->columnSpanFull()
                                        ->afterStateUpdated(function (Set $set): void {
                                            $set('latitude', null);
                                            $set('longitude', null);
                                            $set('map_centre', null);
                                        }),
                                ])->columnSpanFull(),
                            \App\Filament\Forms\DirectorySelectLoading::apply(Select::make('directory_region'), 'регионы')
                                ->label('1. Регион')->searchable()->live()->dehydrated(false)
                                ->helperText('Шаг выбора адреса: сначала выберите регион, затем населённый пункт. При смене региона нижние поля сбрасываются.')
                                ->visible(fn (Get $get): bool => blank($get('physical_site_id')))
                                ->options(fn (): array => app(WarehouseAddressOptions::class)->initial('regions', null))
                                ->getSearchResultsUsing(fn (string $search): array => app(WarehouseAddressOptions::class)->search('regions', null, $search))
                                ->getOptionLabelUsing(fn ($value): ?string => $value ? app(AddressDirectoryClient::class)->cachedLabel($value) ?? $value : null)
                                ->afterStateUpdated(function (Set $set): void {
                                    foreach (['directory_locality', 'directory_street', 'address_external_id', 'address', 'gar_guid', 'kladr_code', 'latitude', 'longitude', 'map_centre'] as $field) {
                                        $set($field, null);
                                    }
                                }),
                            \App\Filament\Forms\DirectorySelectLoading::apply(Select::make('directory_locality'), 'населённые пункты', ['directory_region'])
                                ->options(fn (Get $get): array => $get('directory_region') ? app(WarehouseAddressOptions::class)->initial('localities', $get('directory_region')) : [])
                                ->disabled(fn (Get $get): bool => blank($get('directory_region')))
                                ->visible(fn (Get $get): bool => blank($get('physical_site_id')))
                                ->label('2. Населённый пункт')->searchable()->live()->dehydrated(false)
                                ->searchPrompt('Введите минимум две буквы города, посёлка или села')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => $get('directory_region') ? app(WarehouseAddressOptions::class)->search('localities', $get('directory_region'), $search) : [])
                                ->getOptionLabelUsing(fn ($value): ?string => $value ? app(WarehouseAddressOptions::class)->localityLabel((string) $value) ?? $value : null)
                                ->afterStateUpdated(function (Set $set): void {
                                    foreach (['directory_street', 'address_external_id', 'address', 'gar_guid', 'kladr_code', 'latitude', 'longitude', 'map_centre'] as $field) {
                                        $set($field, null);
                                    }
                                })->helperText('Нужно для поиска здания. Показаны пункты выбранного региона; для остальных введите начало названия.'),
                            \App\Filament\Forms\DirectorySelectLoading::apply(Select::make('directory_street'), 'улицы', ['directory_locality'])
                                ->options(fn (Get $get): array => app(WarehouseAddressOptions::class)->initial('streets', $get('directory_locality'), compact: true))
                                ->visible(fn (Get $get): bool => blank($get('physical_site_id')))
                                ->label('3. Улица')->searchable()->live()->dehydrated(false)
                                ->disabled(fn (Get $get): bool => blank($get('directory_locality')))
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => app(WarehouseAddressOptions::class)->search('streets', $get('directory_locality'), $search, compact: true))
                                ->getOptionLabelUsing(function ($value, ?Warehouse $record): ?string {
                                    if (! $value) {
                                        return null;
                                    }
                                    $short = \Illuminate\Support\Facades\Cache::get('address-directory:short-label:'.sha1((string) $value));
                                    if ($short) {
                                        return $short;
                                    }
                                    $street = collect($record?->address_snapshot['path'] ?? [])->last(fn (array $item): bool => ($item['kind'] ?? null) === 'street' && ($item['externalId'] ?? null) === (string) $value);

                                    return filled($street['name'] ?? null) ? trim(($street['type'] ?? 'ул.').' '.$street['name']) : (string) $value;
                                })
                                ->afterStateUpdated(function (Set $set): void {
                                    foreach (['address_external_id', 'address', 'gar_guid', 'kladr_code', 'latitude', 'longitude'] as $field) {
                                        $set($field, null);
                                    }
                                })->helperText('Необязательно, если здание относится прямо к населённому пункту. Иначе выберите улицу, чтобы не перепутать одинаковые номера домов.'),
                            \App\Filament\Forms\DirectorySelectLoading::apply(Select::make('address_external_id'), 'здания', ['directory_locality', 'directory_street'])
                                ->options(fn (Get $get): array => app(WarehouseAddressOptions::class)->initial('buildings', $get('directory_street') ?: $get('directory_locality'), compact: true))
                                ->visible(fn (Get $get): bool => blank($get('physical_site_id')))
                                ->label('4. Дом / корпус / строение')
                                ->helperText('Обязательно для собственного склада без выбранной площадки. Выберите конкретное здание из справочника. Если его ещё нет в выдаче, адрес не подставляется автоматически.')
                                ->required(fn ($get) => $get('source_type') === 'physical' && blank($get('physical_site_id')))
                                ->searchable()
                                ->live()
                                ->searchPrompt('Введите номер дома')
                                ->disabled(fn (Get $get): bool => blank($get('directory_locality')))
                                ->noSearchResultsMessage('Здания не найдены. Проверьте населённый пункт, улицу и номер.')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => app(WarehouseAddressOptions::class)->search(
                                    'buildings', $get('directory_street') ?: $get('directory_locality'), $search, compact: true))
                                ->getOptionLabelUsing(function ($value, ?Warehouse $record): ?string {
                                    if (blank($value)) {
                                        return null;
                                    }

                                    $short = \Illuminate\Support\Facades\Cache::get('address-directory:short-label:'.sha1((string) $value));
                                    if ($short) {
                                        return $short;
                                    }
                                    if (($record?->address_snapshot['selectionId'] ?? $record?->address_external_id) === (string) $value) {
                                        $number = $record?->address_snapshot['selectedBuildingNumber']
                                            ?? (str_contains((string) $value, '::') ? rawurldecode(explode('::', (string) $value, 2)[1]) : null)
                                            ?? $record?->address_snapshot['target']['name'] ?? null;

                                        return $number ? 'д. '.$number : (string) $value;
                                    }

                                    return (string) $value;
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
                                    try {
                                        $resolved = app(\App\Services\Address\WarehouseAddressResolver::class)->resolve((string) $state);
                                        $point = app(\App\Services\Address\BuildingCoordinates::class)->find($resolved['address_snapshot']);
                                        $city = collect($resolved['address_snapshot']['path'] ?? [])->last(fn (array $row): bool => ($row['kind'] ?? null) === 'locality');
                                        $centre = $city ? app(\App\Services\Address\BuildingCoordinates::class)->centre($city) : null;
                                        $set('map_centre', $centre);
                                        $set('address', $resolved['address']);
                                        $set('latitude', $point['latitude'] ?? null);
                                        $set('longitude', $point['longitude'] ?? null);
                                    } catch (\Illuminate\Validation\ValidationException $error) {
                                        \Filament\Notifications\Notification::make()->title('Не удалось проверить здание. Повторите выбор.')->danger()->send();
                                    }
                                }),

                            Text::make(fn (Get $get): string => $get('address') ?: 'Выберите здание, чтобы указать адрес склада.')
                                ->columnSpanFull(),
                            \Filament\Forms\Components\Hidden::make('latitude'),
                            \Filament\Forms\Components\Hidden::make('longitude'),
                            \Filament\Forms\Components\Hidden::make('map_centre')->dehydrated(false),
                            \Filament\Forms\Components\ViewField::make('_warehouse_map')->view('filament.forms.warehouse-map')->dehydrated(false)->columnSpanFull(),
                        ])->columns(2),
                    Tab::make('Доставка')->schema([
                        Section::make('Подготовка заказа')->description('Время до отправки. При расчёте прибавляется к сроку перевозки из профиля доставки.')->schema([
                            TextInput::make('processing_days_min')->label('Подготовка от')->numeric()->minValue(0)->default(0)->suffix('дн.')
                                ->helperText('Необязательно менять: по умолчанию 0 дней. Минимальное время на подготовку товара к отправке.'),
                            TextInput::make('processing_days_max')->label('Подготовка до')->numeric()->minValue(0)->gte('processing_days_min')->default(0)->suffix('дн.')
                                ->helperText('Необязательно менять: по умолчанию 0 дней. Максимум не может быть меньше значения «от».'),
                        ])->columns(2),
                        Text::make('Сначала сохраните склад — затем здесь можно будет настроить территории, цены и сроки.')
                            ->visible(fn (?Warehouse $record): bool => ! $record?->exists),
                        Livewire::make(DeliveryProfilesRelationManager::class, fn (?Warehouse $record): array => [
                            'ownerRecord' => $record, 'pageClass' => EditWarehouse::class,
                        ])->key('warehouse-delivery-profiles')->visible(fn (?Warehouse $record): bool => (bool) $record?->exists),
                    ]),
                ]),

                Section::make('Дополнительные данные')
                    ->hidden()
                    ->schema([
                        Textarea::make('meta')
                            ->dehydrated(false)
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
