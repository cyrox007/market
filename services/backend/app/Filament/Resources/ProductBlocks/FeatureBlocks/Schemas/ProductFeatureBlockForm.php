<?php

namespace App\Filament\Resources\ProductBlocks\FeatureBlocks\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductFeatureBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->description('Основные данные блока фич')
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Например: "Доставка от 1 дня"')
                            ->columnSpanFull(),

                        TextInput::make('subtitle')
                            ->label('Подзаголовок')
                            ->maxLength(255)
                            ->helperText('Например: "Бесплатно от 30 000 ₽"')
                            ->columnSpanFull(),

                        TextInput::make('icon')
                            ->label('Иконка RemixIcon (опционально)')
                            ->maxLength(255)
                            ->helperText('Класс иконки из библиотеки RemixIcon (например: ri-truck-line). Используется только если не загружено изображение.')
                            ->columnSpanFull(),

                        TextInput::make('icon_color')
                            ->label('Цвет иконки')
                            ->maxLength(50)
                            ->default('red-600')
                            ->helperText('Цвет иконки в формате Tailwind CSS (например: red-600, green-600, blue-600)'),

                        TextInput::make('bg_color')
                            ->label('Цвет фона иконки')
                            ->maxLength(50)
                            ->default('red-100')
                            ->helperText('Цвет фона иконки в формате Tailwind CSS (например: red-100, green-100, blue-100)'),

                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше блок в списке'),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true)
                            ->helperText('Показывать блок на сайте'),
                    ])->columns(2),

                Section::make('Изображение иконки')
                    ->description('Загрузите изображение для иконки блока. Если изображение загружено, оно будет использоваться вместо класса иконки RemixIcon.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('icon_image')
                            ->collection('icon')
                            ->label('Изображение иконки')
                            ->helperText('Изображение для иконки блока. Поддерживаются форматы: JPEG, PNG, WebP, SVG. Будет автоматически создана миниатюра 100x100px.')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
