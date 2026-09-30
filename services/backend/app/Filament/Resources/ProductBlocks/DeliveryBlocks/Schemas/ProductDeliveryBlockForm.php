<?php

namespace App\Filament\Resources\ProductBlocks\DeliveryBlocks\Schemas;

use App\Filament\Support\LucideIconSelect;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductDeliveryBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->description('Основные данные блока доставки')
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Например: "Доставка по городу"')
                            ->columnSpanFull(),

                        RichEditor::make('description')
                            ->label('Описание')
                            ->maxLength(2000)
                            ->helperText('Подробное описание блока доставки')
                            ->columnSpanFull(),

                        LucideIconSelect::make('icon')
                            ->label('Иконка Lucide (опционально)')
                            ->helperText('Используется только если не загружено изображение иконки.')
                            ->columnSpanFull(),

                        TextInput::make('icon_color')
                            ->label('Цвет иконки')
                            ->maxLength(50)
                            ->default('red-600')
                            ->helperText('Цвет иконки в формате Tailwind CSS (например: red-600, yellow-600, green-600)'),

                        TextInput::make('bg_color')
                            ->label('Цвет фона иконки')
                            ->maxLength(50)
                            ->default('red-100')
                            ->helperText('Цвет фона иконки в формате Tailwind CSS (например: red-100, yellow-100, green-100)'),

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
                    ->description('Загрузите изображение для иконки блока. Если изображение загружено, оно будет использоваться вместо выбранной иконки Lucide.')
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
