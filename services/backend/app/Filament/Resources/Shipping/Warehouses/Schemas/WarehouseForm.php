<?php

namespace App\Filament\Resources\Shipping\Warehouses\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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

                Section::make('Правила доставки')
                    ->description('Для каждой территории задайте стоимость и срок доставки именно с этого склада. Правило родительской локации наследуется дочерними, если для них нет собственного правила.')
                    ->schema([
                        Repeater::make('deliveryRules')
                            ->hiddenLabel()
                            ->relationship()
                            ->schema([
                                Select::make('shipping_location_id')
                                    ->label('Территория / регион')
                                    ->relationship('location', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                TextInput::make('delivery_price')
                                    ->label('Стоимость доставки')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('₽')
                                    ->helperText('Пусто — использовать стоимость локации.'),
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
                                    ->label('Приоритет')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Чем больше число, тем выше вариант в выдаче.'),
                                Toggle::make('is_active')
                                    ->label('Активно')
                                    ->default(true),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Добавить правило доставки')
                            ->reorderable(false)
                            ->columnSpanFull(),
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
