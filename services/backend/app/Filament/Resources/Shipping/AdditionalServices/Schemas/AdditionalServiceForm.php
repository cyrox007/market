<?php

namespace App\Filament\Resources\Shipping\AdditionalServices\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdditionalServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название услуги')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set) {
                                if (!$state) {
                                    return;
                                }
                                $set('slug', \Str::slug($state));
                            }),

                        TextInput::make('slug')
                            ->label('URL-слаг')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Уникальный идентификатор для URL'),

                        TextInput::make('code')
                            ->label('Код услуги')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Уникальный код услуги (например, assembly, installation)'),

                        TextInput::make('icon')
                            ->label('Иконка (RemixIcon)')
                            ->maxLength(255)
                            ->helperText('Класс иконки из RemixIcon, например: ri-tools-line, ri-install-line')
                            ->placeholder('ri-tools-line'),

                        Textarea::make('description')
                            ->label('Описание')
                            ->rows(3)
                            ->helperText('Описание услуги для клиентов'),

                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Активна')
                            ->default(true),
                    ])->columns(2),

                Section::make('Ценообразование')
                    ->schema([
                        Select::make('price_type')
                            ->label('Тип цены')
                            ->options([
                                'fixed' => 'Фиксированная цена',
                                'from' => 'Цена "от X"',
                                'custom' => 'Цена отдельно (указывается менеджером)',
                            ])
                            ->required()
                            ->default('fixed')
                            ->helperText('Тип цены для услуги')
                            ->live(),

                        TextInput::make('base_price')
                            ->label('Базовая цена')
                            ->numeric()
                            ->prefix('₽')
                            ->step(0.01)
                            ->minValue(0)
                            ->visible(fn ($get) => in_array($get('price_type'), ['fixed', 'from']))
                            ->required(fn ($get) => in_array($get('price_type'), ['fixed', 'from']))
                            ->helperText('Базовая цена услуги (для типов "фиксированная" и "от X")'),
                    ])->columns(2),
            ]);
    }
}
