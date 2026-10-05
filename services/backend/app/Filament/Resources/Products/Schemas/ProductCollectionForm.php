<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductCollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->description('Основные данные о подборке товаров')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название подборки')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                if (filled($state) && blank($get('slug'))) {
                                    $set('slug', Str::slug($state));
                                }
                            })
                            ->helperText('Например: "Популярное", "Новинки", "Акции"')
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('Системный адрес (slug)')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Заполняется из названия автоматически. Для блока «Хит продаж» на главной укажите featured.')
                            ->columnSpanFull(),

                        Select::make('scope_type')
                            ->label('Тип скоупа')
                            ->options([
                                'featured' => 'Популярное (units_sold > 0)',
                                'new' => 'Новинки (за последние 30 дней)',
                                'sale' => 'Акции (original_price > price)',
                            ])
                            ->helperText('Обязателен для автоматической подборки. После сохранения товары подтянутся по правилам скоупа.')
                            ->live()
                            ->required(fn ($get) => (bool) $get('is_auto'))
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                if ($state && blank($get('slug'))) {
                                    $set('slug', $state);
                                }
                            }),

                        Toggle::make('is_auto')
                            ->label('Автоматическая подборка')
                            ->helperText('При сохранении товары заполнятся автоматически по типу скоупа (нужен scope_type).')
                            ->default(false)
                            ->live(),

                        Toggle::make('is_active')
                            ->label('Активна')
                            ->helperText('Только активные подборки отдаются в API')
                            ->default(true),

                        TextInput::make('priority')
                            ->label('Приоритет')
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше подборка в списке'),

                        TextInput::make('limit')
                            ->label('Лимит товаров')
                            ->numeric()
                            ->default(12)
                            ->required()
                            ->minValue(1)
                            ->helperText('Максимальное количество товаров в подборке'),
                    ])
                    ->columns(2),
            ]);
    }
}
