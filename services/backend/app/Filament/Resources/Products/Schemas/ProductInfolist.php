<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product\Product;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Vanilo\Product\Models\ProductState;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->description('Название, артикул и описание товара')
                    ->schema([
                        SpatieMediaLibraryImageEntry::make('images')
                            ->label('Миниатюра')
                            ->collection('images')
                            ->conversion('thumb')
                            ->imageSize('6rem')
                            ->columnSpanFull(),

                        TextEntry::make('name')
                            ->label('Название')
                            ->weight('bold')
                            ->size('lg')
                            ->columnSpanFull(),
                        TextEntry::make('subtitle')
                            ->label('Подзаголовок')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('sku')
                            ->label('Артикул')
                            ->placeholder('—'),
                        TextEntry::make('slug')
                            ->label('ЧПУ')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('excerpt')
                            ->label('Краткое описание')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('description')
                            ->label('Описание')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible(),

                Section::make('Цена и наличие')
                    ->description('Стоимость, остаток и статус')
                    ->schema([
                        TextEntry::make('price')
                            ->label('Цена')
                            ->money('RUB')
                            ->placeholder('—'),
                        TextEntry::make('original_price')
                            ->label('Зачёркнутая цена')
                            ->money('RUB')
                            ->placeholder('—'),
                        TextEntry::make('stock')
                            ->label('Остаток')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('state')
                            ->label('Статус')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state ? (match ($state) {
                                ProductState::ACTIVE => 'Активен',
                                ProductState::DRAFT => 'Черновик',
                                ProductState::INACTIVE => 'Неактивен',
                                ProductState::UNLISTED => 'Скрыт',
                                ProductState::UNAVAILABLE => 'Недоступен',
                                ProductState::RETIRED => 'Снят с продажи',
                                default => $state,
                            }) : '—')
                            ->color(fn (?string $state): string => $state ? (match ($state) {
                                ProductState::ACTIVE => 'success',
                                ProductState::DRAFT => 'gray',
                                ProductState::INACTIVE => 'warning',
                                ProductState::UNLISTED => 'info',
                                ProductState::UNAVAILABLE => 'danger',
                                ProductState::RETIRED => 'danger',
                                default => 'gray',
                            }) : 'gray'),
                        TextEntry::make('units_sold')
                            ->label('Продано')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('last_sale_at')
                            ->label('Последняя продажа')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('backorder')
                            ->label('Дозаказ')
                            ->formatStateUsing(fn ($state) => $state ? 'Разрешён' : 'Запрещён')
                            ->placeholder('—'),
                        TextEntry::make('priority')
                            ->label('Приоритет')
                            ->numeric()
                            ->placeholder('—'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Категории')
                    ->description('Категории каталога, к которым относится товар')
                    ->schema([
                        RepeatableEntry::make('taxons')
                            ->label('')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('')
                                    ->icon('heroicon-o-folder')
                                    ->badge(),
                            ])
                            ->columns(5),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->extraAttributes(['class' => 'product-infolist-taxons-section']),

                Section::make('Главное фото')
                    ->description('Миниатюра товара')
                    ->schema([
                        SpatieMediaLibraryImageEntry::make('images')
                            ->label('')
                            ->collection('images')
                            ->conversion('thumb')
                            ->imageSize('8rem')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible(),

                Section::make('Галерея')
                    ->description('Дополнительные изображения товара')
                    ->schema([
                        SpatieMediaLibraryImageEntry::make('gallery')
                            ->label('')
                            ->collection('gallery')
                            ->conversion('thumb')
                            ->imageSize('6rem')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),

                Section::make('Характеристики')
                    ->description('Параметры и атрибуты товара')
                    ->schema([
                        RepeatableEntry::make('attributes_for_infolist')
                            ->label('')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Характеристика')
                                    ->weight('medium'),
                                TextEntry::make('value')
                                    ->label('Значение')
                                    ->placeholder('—'),
                            ])
                            ->columns(2),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),

                Section::make('Габариты и вес')
                    ->description('Размеры и масса для доставки')
                    ->schema([
                        TextEntry::make('length')
                            ->label('Длина, см')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('width')
                            ->label('Ширина, см')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('height')
                            ->label('Высота, см')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('weight')
                            ->label('Вес, кг')
                            ->numeric()
                            ->placeholder('—'),
                    ])
                    ->columns(4)
                    ->collapsible()
                    ->collapsed(),

                Section::make('Дополнительно')
                    ->description('GTIN, налог и доставка')
                    ->schema([
                        TextEntry::make('gtin')
                            ->label('GTIN')
                            ->placeholder('—'),
                        TextEntry::make('tax_category_id')
                            ->label('Налоговая категория')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('shipping_category_id')
                            ->label('Категория доставки')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Создан')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label('Обновлён')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Section::make('SEO')
                    ->description('Мета-теги для поисковиков')
                    ->schema([
                        TextEntry::make('ext_title')
                            ->label('Внешний заголовок')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('meta_keywords')
                            ->label('Ключевые слова')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('meta_description')
                            ->label('Мета-описание')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
