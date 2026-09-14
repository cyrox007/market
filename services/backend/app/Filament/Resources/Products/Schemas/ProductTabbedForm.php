<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use RalphJSmit\Filament\SEO\SEO;
use Vanilo\Product\Models\ProductState;

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
                                self::coreSection(),
                                self::technicalSection(),
                            ]),

                        Tab::make('Описание')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                self::descriptionSection(),
                            ]),

                        Tab::make('Характеристики')
                            ->icon('heroicon-m-adjustments-horizontal')
                            ->schema([
                                self::operatorAttributesSection(),
                            ]),

                        Tab::make('Остатки')
                            ->icon('heroicon-m-building-storefront')
                            ->schema([
                                self::stockSection(),
                                self::warehouseStocksSection(),
                            ]),

                        Tab::make('Доставка')
                            ->icon('heroicon-m-truck')
                            ->schema([
                                Grid::make(['default' => 1, 'xl' => 2])
                                    ->schema([
                                        self::dimensionsSection()->collapsed(false),
                                        self::taxShippingSection()->collapsed(false),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Медиа')
                            ->icon('heroicon-m-photo')
                            ->schema([
                                self::mediaSection(),
                            ]),

                        Tab::make('Дополнительно')
                            ->icon('heroicon-m-cog-6-tooth')
                            ->schema([
                                Grid::make(['default' => 1, 'xl' => 2])
                                    ->schema([
                                        self::manufacturerSection(),
                                        self::importSection(),
                                    ])
                                    ->columnSpanFull(),
                                self::seoSection(),
                            ]),
                    ])
                    ->contained(false)
                    ->scrollable()
                    ->persistTab()
                    ->id('product-form-tabs')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The first screen contains only the fields an operator changes most often.
     * Everything technical is intentionally moved below into a collapsed block.
     */
    protected static function coreSection(): Section
    {
        return Section::make('Карточка товара')
            ->schema([
                Grid::make(12)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament/admin_sv/product_resource.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') === '' || $get('slug') === null ? $set('slug', Str::slug($state)) : null)
                            ->columnSpan(8),

                        Select::make('state')
                            ->label(__('filament/admin_sv/product_resource.state'))
                            ->options([
                                ProductState::DRAFT => 'Черновик',
                                ProductState::ACTIVE => 'Активен',
                                ProductState::INACTIVE => 'Неактивен',
                            ])
                            ->required()
                            ->default(ProductState::DRAFT)
                            ->columnSpan(4),

                        Select::make('taxons')
                            ->label(__('filament/admin_sv/product_resource.taxons'))
                            ->relationship('taxons', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpan(6),

                        TextInput::make('price')
                            ->label(__('filament/admin_sv/product_resource.price'))
                            ->numeric()
                            ->prefix('₽')
                            ->required()
                            ->columnSpan(3),

                        TextInput::make('original_price')
                            ->label(__('filament/admin_sv/product_resource.original_price'))
                            ->numeric()
                            ->prefix('₽')
                            ->columnSpan(3),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    protected static function technicalSection(): Section
    {
        return Section::make('Служебные поля')
            ->description('Редко меняются вручную')
            ->schema([
                TextInput::make('slug')
                    ->label(__('filament/admin_sv/product_resource.slug'))
                    ->maxLength(255)
                    ->unique(Product::class, 'slug', ignoreRecord: true),

                TextInput::make('sku')
                    ->label(__('filament/admin_sv/product_resource.sku'))
                    ->maxLength(255),

                TextInput::make('gtin')
                    ->label(__('filament/admin_sv/product_resource.gtin'))
                    ->maxLength(255),

                TextInput::make('priority')
                    ->label(__('filament/admin_sv/product_resource.priority'))
                    ->numeric()
                    ->default(0),
            ])
            ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
            ->collapsible()
            ->collapsed();
    }

    protected static function descriptionSection(): Section
    {
        return Section::make('Описание товара')
            ->schema([
                Textarea::make('description')
                    ->label(__('filament/admin_sv/product_resource.description'))
                    ->default('')
                    ->rows(11)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Operator edits values only. Attribute structure itself is managed in the
     * dedicated «Характеристики» resource.
     */
    protected static function operatorAttributesSection(): Section
    {
        return Section::make('Характеристики товара')
            ->description('Цвет, коммерческий размер, материал и другие свойства')
            ->schema([
                Repeater::make('product_attributes')
                    ->label('')
                    ->schema([
                        Select::make('attribute_id')
                            ->label('Характеристика')
                            ->options(function (?Product $record) {
                                $query = Attribute::query()->orderBy('name');
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
                            ->label('Значение')
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
                                if (! empty($state)) {
                                    $set('custom_value', null);
                                }
                            }),

                        TextInput::make('custom_value')
                            ->label('Ручное значение')
                            ->maxLength(500)
                            ->visible(function ($get) {
                                $attributeId = $get('attribute_id');
                                return $attributeId && (bool) Attribute::query()->find($attributeId)?->allow_custom_value;
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state !== null && $state !== '') {
                                    $set('attribute_value_id', null);
                                }
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'lg' => 2])
                    ->compact()
                    ->defaultItems(0)
                    ->addActionLabel('Добавить характеристику')
                    ->collapsible()
                    ->itemLabel(function (array $state): string {
                        $attribute = Attribute::query()->find($state['attribute_id'] ?? null);
                        if (! $attribute) {
                            return 'Характеристика';
                        }

                        $valueId = $state['attribute_value_id'] ?? null;
                        if (is_array($valueId)) {
                            return $attribute->name . ' (несколько значений)';
                        }
                        if ($valueId) {
                            $value = AttributeValue::query()->find($valueId);
                            return $attribute->name . ($value ? ': ' . $value->value : '');
                        }
                        if (! empty($state['custom_value'])) {
                            return $attribute->name . ': ' . $state['custom_value'];
                        }

                        return $attribute->name;
                    })
                    ->columnSpanFull(),
            ]);
    }

    protected static function mediaSection(): Section
    {
        return Section::make('Изображения товара')
            ->schema([
                Grid::make(['default' => 1, 'xl' => 2])
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('main_image')
                            ->label('Главное изображение')
                            ->collection('main_image')
                            ->image()
                            ->imageEditor()
                            ->imageCropAspectRatio('1:1')
                            ->maxSize(10240),

                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label(__('filament/admin_sv/product_resource.images'))
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->imageEditor()
                            ->maxFiles(20)
                            ->maxSize(10240),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function seoSection(): Section
    {
        return Section::make('SEO')
            ->schema([
                SEO::make()
                    ->columnSpanFull(),
            ])
            ->columns(1)
            ->collapsible()
            ->collapsed();
    }
}
