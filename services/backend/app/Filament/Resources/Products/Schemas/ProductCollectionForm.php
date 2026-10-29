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
                            ->label('Правило автоматического отбора')
                            ->options([
                                'featured' => 'По продажам — только товары с продажами',
                                'new' => 'Новинки (за последние 30 дней)',
                                'sale' => 'Акции (original_price > price)',
                            ])
                            ->helperText('Используется только при включённом автоматическом отборе. Для ручной подборки выключите его и добавьте товары в список ниже.')
                            ->live()
                            ->required(fn ($get) => (bool) $get('is_auto'))
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                if ($state && blank($get('slug'))) {
                                    $set('slug', $state);
                                }
                            }),

                        Toggle::make('is_auto')
                            ->label('Автоматическая подборка')
                            ->helperText('Выключено — товары и их порядок задаются вручную в списке ниже. Включено — отбор по правилу; без статистики продаж автоматическое «Популярное» будет пустым.')
                            ->default(false)
                            ->live(),

                        Toggle::make('is_active')
                            ->label('Активна')
                            ->helperText('Только активные подборки отдаются в API')
                            ->default(true),

                        Toggle::make('recommend_collection_members')
                            ->label('Рекомендовать все товары серии друг у друга')
                            ->helperText('Массовые сопутствующие: включите для ручной подборки, например «Бруно». На карточке каждого товара показываются другие товары этой серии (до 24 карточек), без ручного связывания. Цветовые предложения остаются внутри родительских карточек. Режимы «Только вручную» и «Выключено» у товара не переопределяются.')
                            ->default(false)
                            ->visible(fn ($get): bool => ! (bool) $get('is_auto') && blank($get('scope_type'))),

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
