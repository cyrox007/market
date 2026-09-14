<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Manufacturer;
use App\Models\Product\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Vanilo\Product\Models\ProductState;

/**
 * Filament-native proof of concept for the operator product editor.
 *
 * ProductResource/EditRecord, Filament fields, uploads and relation managers stay intact.
 * Only page composition and information hierarchy change.
 */
class ProductOperatorPocForm extends ProductClassicForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        self::operatorMainSection(),
                        self::operatorSalesSection(),
                    ])
                    ->extraAttributes([
                        'id' => 'product-main',
                        'class' => 'scroll-mt-28',
                    ])
                    ->columnSpanFull(),

                self::operatorDescriptionSection()
                    ->extraAttributes([
                        'id' => 'product-description',
                        'class' => 'scroll-mt-28',
                    ])
                    ->columnSpanFull(),

                self::operatorAttributesSection()
                    ->extraAttributes([
                        'id' => 'product-attributes',
                        'class' => 'scroll-mt-28',
                    ])
                    ->columnSpanFull(),

                Section::make('Остатки')
                    ->description('Складские остатки и предзаказ')
                    ->schema([
                        self::stockSection(),
                        self::warehouseStocksSection(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->extraAttributes([
                        'id' => 'product-stock',
                        'class' => 'scroll-mt-28',
                    ])
                    ->columnSpanFull(),

                self::imagesSection()
                    ->collapsible()
                    ->collapsed()
                    ->extraAttributes([
                        'id' => 'product-media',
                        'class' => 'scroll-mt-28',
                    ])
                    ->columnSpanFull(),

                Section::make('Дополнительно')
                    ->description('Редко используемые настройки товара')
                    ->schema([
                        self::dimensionsSection()->collapsible()->collapsed(),
                        self::taxShippingSection()->collapsible()->collapsed(),
                        self::seoSection(),
                        self::importSection(),
                        self::technicalSection(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->extraAttributes([
                        'id' => 'product-extra',
                        'class' => 'scroll-mt-28',
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function operatorMainSection(): Section
    {
        return Section::make('Основная информация')
            ->description('То, что оператор меняет чаще всего')
            ->schema([
                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set, $get) => blank($get('slug')) ? $set('slug', Str::slug($state)) : null)
                    ->columnSpanFull(),

                Select::make('taxons')
                    ->label('Категории')
                    ->relationship('taxons', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),

                Grid::make(2)
                    ->schema([
                        TextInput::make('sku')
                            ->label('Артикул')
                            ->maxLength(255),

                        Select::make('manufacturer_id')
                            ->label('Производитель')
                            ->options(Manufacturer::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    protected static function operatorSalesSection(): Section
    {
        return Section::make('Продажа')
            ->description('Статус и основные цены')
            ->schema([
                Select::make('state')
                    ->label('Статус')
                    ->options([
                        ProductState::DRAFT => 'Черновик',
                        ProductState::ACTIVE => 'Активен',
                        ProductState::INACTIVE => 'Неактивен',
                    ])
                    ->required()
                    ->default(ProductState::DRAFT)
                    ->columnSpanFull(),

                Grid::make(2)
                    ->schema([
                        TextInput::make('price')
                            ->label('Цена')
                            ->numeric()
                            ->prefix('₽')
                            ->required(),

                        TextInput::make('original_price')
                            ->label('Старая цена')
                            ->numeric()
                            ->prefix('₽'),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    protected static function operatorDescriptionSection(): Section
    {
        return Section::make('Описание')
            ->description('Текст карточки товара на сайте')
            ->schema([
                Textarea::make('description')
                    ->hiddenLabel()
                    ->default('')
                    ->rows(6)
                    ->columnSpanFull(),
            ]);
    }

    protected static function operatorAttributesSection(): Section
    {
        return Section::make('Характеристики')
            ->description('Цвет, размер, материал и другие свойства товара')
            ->schema([
                Repeater::make('product_attributes')
                    ->hiddenLabel()
                    ->table([
                        TableColumn::make('Характеристика')->markAsRequired(),
                        TableColumn::make('Значение'),
                        TableColumn::make('Своё значение'),
                    ])
                    ->schema([
                        Select::make('attribute_id')
                            ->hiddenLabel()
                            ->options(function (?Product $record) {
                                $query = Attribute::query()->orderBy('sort_order')->orderBy('name');

                                if ($record?->is_variable) {
                                    $query->where('is_use_in_variations', false);
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($set) {
                                $set('attribute_value_id', null);
                                $set('custom_value', null);
                            }),

                        Select::make('attribute_value_id')
                            ->hiddenLabel()
                            ->options(function ($get) {
                                $attributeId = $get('attribute_id');
                                if (! $attributeId) {
                                    return [];
                                }

                                return AttributeValue::query()
                                    ->where('attribute_id', $attributeId)
                                    ->orderBy('sort_order')
                                    ->orderBy('value')
                                    ->pluck('value', 'id');
                            })
                            ->multiple(function ($get) {
                                $attributeId = $get('attribute_id');

                                return $attributeId && (bool) Attribute::query()->find($attributeId)?->is_multiple;
                            })
                            ->searchable()
                            ->live()
                            ->disabled(fn ($get) => ! $get('attribute_id'))
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state !== null && $state !== '' && $state !== []) {
                                    $set('custom_value', null);
                                }
                            }),

                        TextInput::make('custom_value')
                            ->hiddenLabel()
                            ->maxLength(1000)
                            ->visible(function ($get) {
                                $attributeId = $get('attribute_id');

                                return $attributeId && (bool) Attribute::query()->find($attributeId)?->allow_custom_value;
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state !== null && trim((string) $state) !== '') {
                                    $set('attribute_value_id', null);
                                }
                            }),
                    ])
                    ->compact()
                    ->defaultItems(0)
                    ->addActionLabel('Добавить характеристику')
                    ->columnSpanFull(),
            ]);
    }

    protected static function technicalSection(): Section
    {
        return Section::make('Служебные данные')
            ->schema([
                TextInput::make('slug')
                    ->label('ЧПУ')
                    ->maxLength(255)
                    ->unique(Product::class, 'slug', ignoreRecord: true),

                TextInput::make('gtin')
                    ->label('GTIN')
                    ->maxLength(255),

                TextInput::make('priority')
                    ->label('Приоритет')
                    ->numeric()
                    ->default(0),
            ])
            ->columns(3)
            ->collapsible()
            ->collapsed();
    }
}
