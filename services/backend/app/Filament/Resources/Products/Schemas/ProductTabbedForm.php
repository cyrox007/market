<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product\Product;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductTabbedForm extends ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('product_editor')
                    ->tabs([
                        Tab::make('Основное')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 2])
                                    ->schema([
                                        Group::make([
                                            self::identitySection(),
                                        ]),
                                        Group::make([
                                            self::categoriesSection(),
                                            self::statusPriceSection(),
                                        ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Описание')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                self::descriptionSection(),
                            ]),

                        Tab::make('Характеристики')
                            ->icon('heroicon-m-adjustments-horizontal')
                            ->schema([
                                self::colorSection()->collapsed(false),
                                self::attributesSection()->collapsed(false),
                            ]),

                        Tab::make('Остатки и доставка')
                            ->icon('heroicon-m-truck')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 2])
                                    ->schema([
                                        Group::make([
                                            self::stockSection(),
                                            self::warehouseStocksSection(),
                                        ]),
                                        Group::make([
                                            self::dimensionsSection()->collapsed(false),
                                            self::taxShippingSection()->collapsed(false),
                                        ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Изображения')
                            ->icon('heroicon-m-photo')
                            ->schema([
                                self::imagesSection(),
                            ]),

                        Tab::make('SEO и 1С')
                            ->icon('heroicon-m-cog-6-tooth')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 2])
                                    ->schema([
                                        Group::make([
                                            self::seoSection()->collapsed(false),
                                        ]),
                                        Group::make([
                                            self::importSection()->collapsed(false),
                                        ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->contained(false)
                    ->scrollable(false)
                    ->persistTab()
                    ->id('product-form-tabs')
                    ->columnSpanFull(),
            ]);
    }

    protected static function identitySection(): Section
    {
        return Section::make('Основная информация')
            ->description('Название и идентификаторы товара')
            ->schema([
                TextInput::make('name')
                    ->label(__('filament/admin_sv/product_resource.name'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') === '' || $get('slug') === null ? $set('slug', Str::slug($state)) : null)
                    ->helperText('Полное название товара для отображения на сайте')
                    ->columnSpanFull(),

                TextInput::make('slug')
                    ->label(__('filament/admin_sv/product_resource.slug'))
                    ->maxLength(255)
                    ->unique(Product::class, 'slug', ignoreRecord: true)
                    ->helperText('ЧПУ для URL. Генерируется из названия автоматически, можно изменить при необходимости')
                    ->columnSpanFull(),

                Section::make('Артикулы')
                    ->schema([
                        TextInput::make('sku')
                            ->label(__('filament/admin_sv/product_resource.sku'))
                            ->maxLength(255)
                            ->helperText('Артикул товара (может повторяться). Если оставить пустым, при сохранении будет подставлен ID товара.'),

                        TextInput::make('gtin')
                            ->label(__('filament/admin_sv/product_resource.gtin'))
                            ->maxLength(255)
                            ->helperText('EAN, UPC и т.д.'),
                    ])
                    ->columns(2),
            ])
            ->columns(1);
    }

    protected static function descriptionSection(): Section
    {
        return Section::make('Описание товара')
            ->description('Контент карточки товара на сайте')
            ->schema([
                Textarea::make('description')
                    ->label(__('filament/admin_sv/product_resource.description'))
                    ->default('')
                    ->rows(18)
                    ->helperText('Подробное описание товара. Поддерживается HTML (теги, списки, ссылки).')
                    ->columnSpanFull(),
            ]);
    }
}
