<?php

namespace App\Filament\Resources\Shipping\Warehouses\Schemas;

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

                Section::make('Привязка к локациям доставки')
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
