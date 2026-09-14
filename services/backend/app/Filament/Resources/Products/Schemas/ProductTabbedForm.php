<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
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
                                self::operatorAttributesSection(),
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

    protected static function seoSection(): Section
    {
        return Section::make('SEO')
            ->description('Поисковая оптимизация товара')
            ->schema([
                SEO::make()
                    ->hiddenLabel()
                    ->columnSpanFull(),
            ])
            ->columns(1)
            ->collapsible()
            ->collapsed();
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

    /**
     * Operator-facing status and pricing block.
     * Variability is derived from actual variants instead of a manual toggle.
     */
    protected static function statusPriceSection(): Section
    {
        return Section::make('Статус и цены')
            ->description('Видимость товара и основные цены')
            ->schema([
                Select::make('state')
                    ->label(__('filament/admin_sv/product_resource.state'))
                    ->options([
                        ProductState::DRAFT => 'Черновик',
                        ProductState::ACTIVE => 'Активен',
                        ProductState::INACTIVE => 'Неактивен',
                    ])
                    ->required()
                    ->default(ProductState::DRAFT)
                    ->helperText('Статус видимости на сайте')
                    ->columnSpanFull(),

                TextInput::make('priority')
                    ->label(__('filament/admin_sv/product_resource.priority'))
                    ->numeric()
                    ->default(0)
                    ->helperText('Меньше число — выше в списке')
                    ->columnSpanFull(),

                TextInput::make('price')
                    ->label(__('filament/admin_sv/product_resource.price'))
                    ->numeric()
                    ->prefix('₽')
                    ->required()
                    ->helperText('Цена продажи')
                    ->columnSpanFull(),

                TextInput::make('original_price')
                    ->label(__('filament/admin_sv/product_resource.original_price'))
                    ->numeric()
                    ->prefix('₽')
                    ->helperText('Цена до скидки')
                    ->columnSpanFull(),

                Placeholder::make('variants_info')
                    ->label('Вариации')
                    ->content(function ($record) {
                        if (! $record) {
                            return 'После первого сохранения вариации можно добавить во вкладке «Вариации».';
                        }

                        $variantsCount = $record->variants()->count();

                        return $variantsCount === 0
                            ? 'Вариаций пока нет. Добавьте первую во вкладке «Вариации» — товар автоматически станет вариативным.'
                            : "{$variantsCount} шт. Управление во вкладке «Вариации».";
                    })
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    /**
     * Оператор выбирает только уже заведённые характеристики и значения.
     * Структуру справочника (тип, фильтры, участие в вариациях) меняют в отдельном разделе «Характеристики».
     */
    protected static function operatorAttributesSection(): Section
    {
        return Section::make('Характеристики товара')
            ->description('Цвет, коммерческий размер, материал и другие свойства. Для вариативного товара цвет/размер задаются в его вариациях.')
            ->schema([
                Repeater::make('product_attributes')
                    ->label('Характеристики')
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
                            }),
                    ])
                    ->columns(1)
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
                    ->helperText('Если нужной характеристики или значения нет, добавьте их в разделе «Характеристики» в меню — здесь структура справочника не создаётся.')
                    ->columnSpanFull(),
            ]);
    }

}
