<?php

namespace App\Filament\Resources\Shipping\AdditionalServices\Schemas;

use App\Filament\Support\LucideIconSelect;
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

                        LucideIconSelect::make('icon')
                            ->label('Иконка')
                            ->helperText('Выберите иконку Lucide. В базе хранится стабильный идентификатор, например wrench или truck.'),

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

                Section::make('Тарифы услуги')
                    ->description('Стоимость задаётся отдельно в профиле доставки каждого склада. Глобальной цены и тарифов локаций больше нет.')
                    ->schema([
                        Select::make('price_type')->label('Тип тарифа')
                            ->options(['fixed' => 'Фиксированная стоимость в профиле склада'])
                            ->default('fixed')->required()
                            ->helperText('Старые типы «от» и «по договорённости» не участвуют в автоматическом расчёте. Для использования выберите фиксированный тариф и задайте цену в профиле склада.'),
                    ]),
            ]);
    }
}
