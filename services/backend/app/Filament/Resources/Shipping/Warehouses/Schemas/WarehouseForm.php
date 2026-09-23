<?php

namespace App\Filament\Resources\Shipping\Warehouses\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Vanilo\Shipment\Models\ShippingMethod;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('external_id')
                            ->label('Внешний ID склада (1С, stockId)')
                            ->required()
                            ->maxLength(36)
                            ->unique(ignoreRecord: true)
                            ->helperText('UUID склада из ответа API …/products/{id}/stocks. При синхронизации остатков склад создаётся автоматически, если его ещё нет.'),

                        TextInput::make('name')
                            ->label('Название склада')
                            ->required()
                            ->maxLength(255),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Способы доставки со склада')
                    ->description('Добавьте перевозчиков/способы доставки, затем задайте для каждого собственную географию, стоимость и срок. Более точная локация имеет приоритет над родительской.')
                    ->schema([
                        Repeater::make('deliveryMethods')
                            ->hiddenLabel()
                            ->relationship()
                            ->schema([
                                Select::make('shipping_method_id')
                                    ->label('Способ доставки')
                                    ->relationship(
                                        'shippingMethod',
                                        'name',
                                        modifyQueryUsing: fn ($query) => $query
                                            ->where('is_active', true)
                                            ->with('carrier')
                                            ->orderBy('name')
                                    )
                                    ->getOptionLabelFromRecordUsing(function (ShippingMethod $record): string {
                                        $carrier = $record->carrier?->name;

                                        return $carrier
                                            ? $record->name . ' — ' . $carrier
                                            : $record->name;
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->columnSpan(2),

                                TextInput::make('priority')
                                    ->label('Приоритет способа')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Чем больше число, тем выше способ в выдаче.'),

                                Toggle::make('is_active')
                                    ->label('Активен')
                                    ->default(true),

                                Repeater::make('zones')
                                    ->label('География, тарифы и сроки')
                                    ->relationship()
                                    ->schema([
                                        Select::make('shipping_location_id')
                                            ->label('Территория / регион / город')
                                            ->relationship('location', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->columnSpan(2),

                                        TextInput::make('delivery_price')
                                            ->label('Стоимость')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('₽')
                                            ->helperText('Пусто — использовать тариф локации.'),

                                        TextInput::make('free_delivery_threshold')
                                            ->label('Бесплатно от')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('₽')
                                            ->helperText('Пусто — использовать порог локации.'),

                                        TextInput::make('delivery_days_min')
                                            ->label('Срок от, дней')
                                            ->numeric()
                                            ->minValue(0)
                                            ->helperText('Пусто — использовать срок локации.'),

                                        TextInput::make('delivery_days_max')
                                            ->label('Срок до, дней')
                                            ->numeric()
                                            ->minValue(0)
                                            ->helperText('Пусто — использовать срок локации.'),

                                        TextInput::make('priority')
                                            ->label('Приоритет зоны')
                                            ->numeric()
                                            ->default(0),

                                        Toggle::make('is_active')
                                            ->label('Активна')
                                            ->default(true),
                                    ])
                                    ->columns(4)
                                    ->defaultItems(0)
                                    ->addActionLabel('Добавить территорию')
                                    ->reorderable(false)
                                    ->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Добавить способ доставки')
                            ->reorderable(false)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),

                Section::make('Дополнительные данные')
                    ->schema([
                        Textarea::make('meta')
                            ->label('Meta (JSON)')
                            ->rows(5)
                            ->helperText('Опционально: JSON с произвольными данными склада.')
                            ->formatStateUsing(fn ($state) => is_array($state)
                                ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                                : $state)
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
