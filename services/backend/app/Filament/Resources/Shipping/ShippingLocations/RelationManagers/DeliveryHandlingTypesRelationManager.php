<?php

namespace App\Filament\Resources\Shipping\ShippingLocations\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;

class DeliveryHandlingTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveryHandlingTypes';

    protected static ?string $title = 'Типы обработки доставки';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Настройки обработки доставки')
                    ->schema([
                        TextInput::make('base_price')
                            ->label('Базовая стоимость обработки')
                            ->numeric()
                            ->prefix('₽')
                            ->helperText('Базовая стоимость обработки (если не зависит от этажа)')
                            ->step(0.01),

                        TextInput::make('elevator_price')
                            ->label('Стоимость с лифтом')
                            ->numeric()
                            ->prefix('₽')
                            ->helperText('Фиксированная стоимость для любого этажа (для типа "Лифт")')
                            ->step(0.01)
                            ->visible(fn ($record) => $record?->requires_elevator ?? false),

                        Repeater::make('floor_prices')
                            ->label('Цены по этажам')
                            ->schema([
                                TextInput::make('floor')
                                    ->label('Этаж')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(1)
                                    ->disabled(fn ($state) => $state !== null),

                                TextInput::make('price')
                                    ->label('Цена')
                                    ->numeric()
                                    ->required()
                                    ->prefix('₽')
                                    ->step(0.01)
                                    ->minValue(0),
                            ])
                            ->defaultItems(1)
                            ->itemLabel(function (array $state): ?string {
                                if (!isset($state['floor']) || !isset($state['price'])) {
                                    return null;
                                }
                                return "Этаж {$state['floor']}: " . ($state['price'] ?? 0) . " ₽";
                            })
                            ->addActionLabel('Добавить этаж')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record?->requires_floor ?? false)
                            ->helperText('Для типов обработки, требующих указания этажа, необходимо указать цены для каждого этажа. Минимум 1 этаж обязателен.'),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),

                        Textarea::make('notes')
                            ->label('Примечания')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),

                TextColumn::make('code')
                    ->label('Код')
                    ->searchable(),

                TextColumn::make('pivot.base_price')
                    ->label('Базовая цена')
                    ->money('RUB')
                    ->default('—'),

                TextColumn::make('pivot.elevator_price')
                    ->label('Цена с лифтом')
                    ->money('RUB')
                    ->default('—'),

                TextColumn::make('pivot.is_active')
                    ->label('Активен')
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn(AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                // При выборе типа обработки проверяем, требует ли он этаж
                                if ($state) {
                                    $handlingType = \App\Models\Shipping\DeliveryHandlingType::find($state);
                                    if ($handlingType && $handlingType->requires_floor) {
                                        // Если требует этаж, устанавливаем значение по умолчанию для floor_prices
                                        $currentFloorPrices = $get('floor_prices');
                                        if (empty($currentFloorPrices)) {
                                            $set('floor_prices', [['floor' => 1, 'price' => 0]]);
                                        }
                                    }
                                }
                            }),
                        TextInput::make('base_price')
                            ->label('Базовая стоимость')
                            ->numeric()
                            ->prefix('₽')
                            ->step(0.01),
                        TextInput::make('elevator_price')
                            ->label('Стоимость с лифтом')
                            ->numeric()
                            ->prefix('₽')
                            ->step(0.01)
                            ->visible(function ($get) {
                                $selectedId = $get('recordId');
                                if ($selectedId) {
                                    $handlingType = \App\Models\Shipping\DeliveryHandlingType::find($selectedId);
                                    return $handlingType?->requires_elevator ?? false;
                                }
                                return false;
                            }),
                        Repeater::make('floor_prices')
                            ->label('Цены по этажам')
                            ->schema([
                                TextInput::make('floor')
                                    ->label('Этаж')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(1)
                                    ->disabled(fn ($state) => $state !== null),

                                TextInput::make('price')
                                    ->label('Цена')
                                    ->numeric()
                                    ->required()
                                    ->prefix('₽')
                                    ->step(0.01)
                                    ->minValue(0),
                            ])
                            ->defaultItems(1)
                            ->itemLabel(function (array $state): ?string {
                                if (!isset($state['floor']) || !isset($state['price'])) {
                                    return null;
                                }
                                return "Этаж {$state['floor']}: " . ($state['price'] ?? 0) . " ₽";
                            })
                            ->addActionLabel('Добавить этаж')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->columnSpanFull()
                            ->visible(function ($get) {
                                $selectedId = $get('recordId');
                                if ($selectedId) {
                                    $handlingType = \App\Models\Shipping\DeliveryHandlingType::find($selectedId);
                                    return $handlingType?->requires_floor ?? false;
                                }
                                return false;
                            })
                            ->required(function ($get) {
                                $selectedId = $get('recordId');
                                if ($selectedId) {
                                    $handlingType = \App\Models\Shipping\DeliveryHandlingType::find($selectedId);
                                    return $handlingType?->requires_floor ?? false;
                                }
                                return false;
                            })
                            ->helperText('Для типов обработки, требующих указания этажа, необходимо указать цены для каждого этажа. Минимум 1 этаж обязателен.'),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                        Textarea::make('notes')
                            ->label('Примечания')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->using(function (array $data, $relationship): void {
                        // Преобразуем floor_prices в JSON перед сохранением
                        if (isset($data['floor_prices']) && is_array($data['floor_prices'])) {
                            $floorPricesJson = [];
                            foreach ($data['floor_prices'] as $item) {
                                if (isset($item['floor']) && isset($item['price'])) {
                                    $floorPricesJson[(string) $item['floor']] = (float) $item['price'];
                                }
                            }
                            $data['floor_prices'] = !empty($floorPricesJson) ? json_encode($floorPricesJson) : null;
                        }

                        $relationship->attach($data['recordId'], Arr::except($data, ['recordId']));
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->form(fn (EditAction $action): array => [
                        TextInput::make('base_price')
                            ->label('Базовая стоимость обработки')
                            ->numeric()
                            ->prefix('₽')
                            ->step(0.01)
                            ->helperText('Базовая стоимость обработки (если не зависит от этажа)'),

                        TextInput::make('elevator_price')
                            ->label('Стоимость с лифтом')
                            ->numeric()
                            ->prefix('₽')
                            ->step(0.01)
                            ->helperText('Фиксированная стоимость для любого этажа (для типа "Лифт")')
                            ->visible(fn ($record) => $record?->requires_elevator ?? false),

                        Repeater::make('floor_prices')
                            ->label('Цены по этажам')
                            ->schema([
                                TextInput::make('floor')
                                    ->label('Этаж')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(1)
                                    ->disabled(fn ($state) => $state !== null),

                                TextInput::make('price')
                                    ->label('Цена')
                                    ->numeric()
                                    ->required()
                                    ->prefix('₽')
                                    ->step(0.01)
                                    ->minValue(0),
                            ])
                            ->defaultItems(1)
                            ->itemLabel(function (array $state): ?string {
                                if (!isset($state['floor']) || !isset($state['price'])) {
                                    return null;
                                }
                                return "Этаж {$state['floor']}: " . ($state['price'] ?? 0) . " ₽";
                            })
                            ->addActionLabel('Добавить этаж')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record?->requires_floor ?? false)
                            ->helperText('Для типов обработки, требующих указания этажа, необходимо указать цены для каждого этажа. Минимум 1 этаж обязателен.'),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),

                        Textarea::make('notes')
                            ->label('Примечания')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        // Преобразуем floor_prices из JSON в массив для Repeater
                        if (isset($data['pivot']['floor_prices'])) {
                            $floorPrices = is_string($data['pivot']['floor_prices'])
                                ? json_decode($data['pivot']['floor_prices'], true)
                                : $data['pivot']['floor_prices'];

                            if (is_array($floorPrices)) {
                                $data['floor_prices'] = collect($floorPrices)->map(function ($price, $floor) {
                                    return [
                                        'floor' => (int) $floor,
                                        'price' => (float) $price,
                                    ];
                                })->values()->toArray();
                            } else {
                                $data['floor_prices'] = [];
                            }
                        } else {
                            $data['floor_prices'] = [];
                        }

                        // Если тип требует этаж и нет цен по этажам, добавляем значение по умолчанию
                        if (($record->requires_floor ?? false) && empty($data['floor_prices'])) {
                            $data['floor_prices'] = [
                                ['floor' => 1, 'price' => 0],
                            ];
                        }

                        // Копируем pivot данные в корень для удобства
                        if (isset($data['pivot'])) {
                            $data['base_price'] = $data['pivot']['base_price'] ?? null;
                            $data['elevator_price'] = $data['pivot']['elevator_price'] ?? null;
                            $data['is_active'] = $data['pivot']['is_active'] ?? true;
                            $data['notes'] = $data['pivot']['notes'] ?? null;
                        }

                        return $data;
                    })
                    ->using(function (array $data, $record): void {
                        // Преобразуем floor_prices обратно в JSON
                        if (isset($data['floor_prices']) && is_array($data['floor_prices'])) {
                            $floorPricesJson = [];
                            foreach ($data['floor_prices'] as $item) {
                                if (isset($item['floor']) && isset($item['price'])) {
                                    $floorPricesJson[(string) $item['floor']] = (float) $item['price'];
                                }
                            }
                            $data['floor_prices'] = !empty($floorPricesJson) ? json_encode($floorPricesJson) : null;
                        }

                        // Обновляем pivot данные
                        $pivotData = Arr::only($data, ['base_price', 'elevator_price', 'floor_prices', 'is_active', 'notes']);
                        $pivotData = array_filter($pivotData, fn ($value) => $value !== null);

                        $this->getOwnerRecord()->deliveryHandlingTypes()->updateExistingPivot(
                            $record->id,
                            $pivotData
                        );
                    }),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
