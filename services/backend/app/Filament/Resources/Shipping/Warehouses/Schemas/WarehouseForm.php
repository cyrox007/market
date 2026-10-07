<?php

namespace App\Filament\Resources\Shipping\Warehouses\Schemas;

use App\Services\Address\AddressDirectoryClient;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                    ->schema([
                        Select::make('address_external_id')
                            ->label('Адрес по КЛАДР/ФИАС')
                            ->searchable()
                            ->live()
                            ->searchPrompt('Введите город, улицу и номер дома')
                            ->searchingMessage('Ищем адрес…')
                            ->noSearchResultsMessage('Дом не найден или адресный сервис недоступен')
                            ->getSearchResultsUsing(function (string $search): array {
                                try {
                                    return collect(app(AddressDirectoryClient::class)->searchBuildings($search))
                                        ->filter(fn (array $item): bool => filled($item['externalId'] ?? null) && filled($item['label'] ?? null))
                                        ->mapWithKeys(fn (array $item): array => [$item['externalId'] => $item['label']])
                                        ->all();
                                } catch (\Throwable) {
                                    return [];
                                }
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                if (blank($value)) {
                                    return null;
                                }

                                try {
                                    return app(AddressDirectoryClient::class)->hierarchy((string) $value)['label'] ?? (string) $value;
                                } catch (\Throwable) {
                                    return (string) $value;
                                }
                            })
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if (blank($state)) {
                                    return;
                                }

                                try {
                                    $address = app(AddressDirectoryClient::class)->hierarchy((string) $state);
                                    $set('address', $address['label'] ?? null);
                                } catch (\Throwable) {
                                    // The editable address below remains available if the classifier is offline.
                                }
                            })
                            ->helperText('Начните вводить полный адрес и выберите конкретный дом. Сохраняется стабильный ID классификатора.')
                            ->columnSpanFull(),

                        TextInput::make('address')
                            ->label('Полный адрес')
                            ->maxLength(255)
                            ->helperText('Заполняется после выбора из КЛАДР. Пока адресный API недоступен, адрес можно указать вручную.')
                            ->columnSpanFull(),
                        TextInput::make('latitude')->label('Широта')->numeric(),
                        TextInput::make('longitude')->label('Долгота')->numeric(),
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
