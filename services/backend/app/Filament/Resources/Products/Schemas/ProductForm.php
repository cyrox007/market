<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RalphJSmit\Filament\SEO\SEO;
use Vanilo\Product\Models\ProductState;
use Vanilo\Shipment\Models\ShippingCategory as ModelsShippingCategory;
use Vanilo\Taxes\Models\TaxCategory;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Product\Manufacturer;
use App\Filament\Forms\WarehouseStocksFormComponents;
use App\Models\Settings\ProductStockSettings;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Две колонки на ПК: слева — основное, справа — категории, цены, изображения и т.д.
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        // Левая колонка
                        Group::make([
                            self::mainInfoSection(),
                            self::stockSection(),
                            self::warehouseStocksSection(),
                            self::colorSection(),
                            self::dimensionsSection(),
                            self::seoSection(),
                        ]),

                        // Правая колонка (изображения здесь, не сверху)
                        Group::make([
                            self::categoriesSection(),
                            /* self::manufacturerSection(), */
                            self::statusPriceSection(),
                            self::imagesSection(),
                            self::taxShippingSection(),
                            self::importSection(),
                        ]),
                    ])
                    ->columnSpanFull(),

                // Характеристики товара — на всю ширину, отдельным блоком ниже, с возможностью свернуть
                self::attributesSection()->columnSpanFull(),
            ]);
    }

    protected static function mainInfoSection(): Section
    {
        return Section::make('Основная информация')
                    ->description('Название, артикул и описание')
                    ->schema([
                        // Заголовки — полная ширина
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

                        /* TextInput::make('subtitle')
                            ->label(__('filament/admin_sv/product_resource.subtitle'))
                            ->maxLength(255)
                            ->helperText('Краткий подзаголовок товара (опционально)')
                            ->columnSpanFull(), */

                        // Описания: Textarea вместо RichEditor из-за бага в Filament 4.3 (TipTap init "length"/getEditor undefined).
                        // HTML в description/excerpt на фронте рендерится как есть; при желании вернуть RichEditor — обновить Filament.
                        /* Textarea::make('excerpt')
                            ->label(__('filament/admin_sv/product_resource.excerpt'))
                            ->default('')
                            ->maxLength(500)
                            ->rows(3)
                            ->helperText('Краткое описание для карточек и списков (до 500 символов). Поддерживается HTML.')
                            ->columnSpanFull(), */

                        Textarea::make('description')
                            ->label(__('filament/admin_sv/product_resource.description'))
                            ->default('')
                            ->rows(12)
                            ->helperText('Подробное описание товара. Поддерживается HTML (теги, списки, ссылки).')
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

    protected static function categoriesSection(): Section
    {
        return Section::make('Категории')
            ->description('Категории товара в каталоге')
            ->schema([
                Select::make('taxons')
                    ->label(__('filament/admin_sv/product_resource.taxons'))
                    ->relationship('taxons', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Выберите одну или несколько категорий, к которым относится товар')
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    protected static function manufacturerSection(): Section
    {
        return Section::make('Производитель')
            ->description('Производитель товара (заполняется при импорте из 1С, можно задать вручную)')
            ->schema([
                Select::make('manufacturer_id')
                    ->label('Производитель')
                    ->options(Manufacturer::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Выберите производителя из списка. Список пополняется при импорте каталога из 1С.')
                    ->columnSpanFull(),
            ])
            ->columns(1)
            ->collapsible()
            ->collapsed();
    }

    protected static function statusPriceSection(): Section
    {
        return Section::make('Статус, активность и цены')
            ->description('Видимость товара, вариативность и цены')
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

                Toggle::make('is_variable')
                    ->label(__('filament/admin_sv/product_resource.is_variable'))
                    ->helperText(function ($record) {
                        if (!$record) {
                            return 'Вариации (цвет, размер) — во вкладке "Вариации товара" после сохранения.';
                        }
                        $variantsCount = $record->variants()->count();
                        if ($variantsCount > 0) {
                            return "У товара {$variantsCount} вариаций. Удалите все, чтобы отключить.";
                        }
                        return 'Вариации добавляются во вкладке "Вариации товара".';
                    })
                    ->default(false)
                    ->disabled(fn($record) => $record && $record->variants()->count() > 0)
                    ->dehydrated()
                    ->columnSpanFull(),

                \Filament\Forms\Components\Placeholder::make('variants_info')
                    ->label('Торговые предложения')
                    ->content(function ($record) {
                        if (!$record) return '—';
                        $variantsCount = $record->variants()->count();
                        return $variantsCount === 0
                            ? 'Нет. Добавьте во вкладке "Вариации товара".'
                            : "{$variantsCount} шт. Управление во вкладке 'Вариации товара'.";
                    })
                    ->visible(fn($record) => $record !== null)
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    protected static function stockSection(): Section
    {
        return Section::make('Склад и наличие')
                    ->description('Управление остатками на складе и возможностью предзаказа')
                    ->visible(fn () => ! ProductStockSettings::getInstance()->warehouse_accounting_enabled)
                    ->schema([
                        TextInput::make('stock')
                            ->label(__('filament/admin_sv/product_resource.stock'))
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Количество единиц товара на складе')
                            ->visible(fn($record) => !$record || !$record->is_variable)
                            ->disabled(fn($record) => $record && $record->is_variable),

                        \Filament\Forms\Components\Placeholder::make('stock_info')
                            ->label('Общий остаток')
                            ->content(function ($record) {
                                if (!$record || !$record->is_variable) {
                                    return '—';
                                }
                                
                                $totalStock = $record->variants()
                                    ->active()
                                    ->sum('stock');
                                
                                return "{$totalStock} шт. (сумма всех вариаций)";
                            })
                            ->visible(fn($record) => $record && $record->is_variable),

                        \Filament\Forms\Components\Placeholder::make('stock_helper')
                            ->label('')
                            ->content('Для вариативных товаров остаток рассчитывается автоматически как сумма остатков всех вариаций. Управление остатками вариаций доступно во вкладке "Вариации товара".')
                            ->visible(fn($record) => $record && $record->is_variable)
                            ->columnSpanFull(),

                        Toggle::make('backorder')
                            ->label(__('filament/admin_sv/product_resource.backorder'))
                            ->helperText('Если товара нет в наличии, разрешить клиентам делать предзаказ')
                            ->default(false)
                            ->columnSpanFull(),

                        TextInput::make('units_sold')
                            ->label(__('filament/admin_sv/product_resource.units_sold'))
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Общее количество проданных единиц (автоматически обновляется)'),
                    ])
                    ->columns(1);
    }

    protected static function warehouseStocksSection(): Section
    {
        return Section::make('Остатки по складам (1С)')
            ->description('Остатки сопоставляются со складами 1С по external_id и подтягиваются автоматически (очередь integration-1c). Для вариативных товаров — у каждой вариации во вкладке «Торговые предложения».')
            ->collapsible()
            ->collapsed(fn ($record) => $record?->is_variable)
            ->visible(fn () => ProductStockSettings::getInstance()->warehouse_accounting_enabled)
            ->schema([
                \Filament\Forms\Components\Placeholder::make('warehouse_variable_hint')
                    ->label('')
                    ->content('У вариативного товара остатки по складам настраиваются у каждой вариации (вкладка «Торговые предложения»). Здесь — только для простого товара без вариаций.')
                    ->visible(fn ($record) => $record && $record->is_variable)
                    ->columnSpanFull(),

                WarehouseStocksFormComponents::warehouseStocksRepeater()
                    ->visible(fn ($record) => ! $record || ! $record->is_variable)
                    ->helperText('Склады создаются при синхронизации из 1С (поле external_id склада). Привязка складов к регионам доставки — в разделе «Доставка → Склады».'),
            ]);
    }

    protected static function imagesSection(): Section
    {
        return Section::make('Изображения товара')
                    ->description('Загрузка главного изображения и галереи. Превью в ряд, компактно.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('images')
                            ->collection('images')
                            ->label('Главное изображение')
                            ->helperText('Основное изображение товара для карточки и страницы товара. Миниатюра 300×300 px создаётся автоматически.')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),

                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->label('Галерея изображений')
                            ->helperText('Дополнительные фото товара (до 20). Превью в ряд.')
                            ->multiple()
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxFiles(20)
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->panelLayout('grid'),
                    ])
                    ->columns(2);
    }

    protected static function attributesSection(): Section
    {
        return Section::make('Характеристики товара')
            ->description('Характеристики с значениями (материал, производитель и т.д.). Компактное отображение.')
            ->collapsible()
            ->collapsed()
            ->schema([
                Repeater::make('product_attributes')
                    ->label('Характеристики')
                    ->afterStateHydrated(function (callable $set, callable $get, ?Product $record): void {
                        $current = $get('product_attributes') ?? [];
                        if (!empty($current)) {
                            return;
                        }

                        if (!$record) {
                            return;
                        }

                        $record->loadMissing('taxons');
                        $taxons = $record->taxons;

                        if ($taxons->isEmpty()) {
                            return;
                        }

                        $slugs = $taxons->pluck('slug')->filter()->unique()->values()->all();
                        if (empty($slugs)) {
                            return;
                        }

                        $categories = Category::query()
                            ->whereIn('slug', $slugs)
                            ->with('variationAttributes')
                            ->get();

                        $defaultAttributes = $categories
                            ->flatMap(fn (Category $category) => $category->variationAttributes ?? collect())
                            ->unique('id')
                            ->values();

                        if ($defaultAttributes->isEmpty()) {
                            return;
                        }

                        $initial = [];
                        foreach ($defaultAttributes as $attribute) {
                            $initial[] = [
                                'attribute_id' => $attribute->id,
                                'attribute_value_id' => null,
                            ];
                        }

                        if (!empty($initial)) {
                            $set('product_attributes', $initial);
                        }
                    })
                    ->schema([
                        Select::make('attribute_id')
                            ->label('Характеристика')
                            ->options(Attribute::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($set) {
                                $set('attribute_value_id', null);
                                $set('custom_value', null);
                            })
                            // 👇 INLINE-СОЗДАНИЕ ХАРАКТЕРИСТИКИ
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Название характеристики')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                                TextInput::make('slug')
                                    ->label('Слаг')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Оставьте пустым для автоматической генерации'),
                                Select::make('type')
                                    ->label('Тип')
                                    ->options([
                                        'select' => 'Список',
                                        'color' => 'Цвет',
                                        'string' => 'Строка',
                                        'text' => 'Текст',
                                        'number' => 'Число',
                                    ])
                                    ->default('select')
                                    ->required(),
                                TextInput::make('sort_order')
                                    ->label('Порядок')
                                    ->numeric()
                                    ->default(0),
                                Toggle::make('is_use_in_variations')
                                    ->label('Участвует в вариациях')
                                    ->default(false),
                                Toggle::make('is_filterable')
                                    ->label('Показывать в фильтрах')
                                    ->default(true),
                                Toggle::make('is_multiple')
                                    ->label('Множественный выбор')
                                    ->default(false)
                                    ->visible(fn ($get) => $get('type') === 'color' || in_array($get('type'), ['select', 'string'], true)),
                                Toggle::make('allow_custom_value')
                                    ->label('Разрешить ручной ввод')
                                    ->default(false)
                                    ->visible(fn ($get) => $get('type') !== 'color'),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $attribute = Attribute::create($data);
                                return $attribute->id;
                            }),
                        
                        Select::make('attribute_value_id')
                            ->label('Значение (из списка)')
                            ->options(function ($get) {
                                $attributeId = $get('attribute_id');
                                if (!$attributeId) return [];
                                
                                return AttributeValue::where('attribute_id', $attributeId)
                                    ->orderBy('sort_order')
                                    ->orderBy('value')
                                    ->pluck('value', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->multiple(function ($get) {
                                $attributeId = $get('attribute_id');
                                if (!$attributeId) return false;
                                $attribute = Attribute::find($attributeId);
                                return $attribute && $attribute->is_multiple;
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if (!empty($state)) {
                                    $set('custom_value', null);
                                }
                            })
                            ->required(function ($get) {
                                $attributeId = $get('attribute_id');
                                if (!$attributeId) return false;
                                $attribute = Attribute::find($attributeId);
                                return ($attribute->is_required ?? false) && empty($get('custom_value'));
                            })
                            ->disabled(fn($get) => !$get('attribute_id'))
                            
                            ->createOptionForm(function ($get) {
                                $attributeId = $get('attribute_id');
                                $attribute = $attributeId ? Attribute::find($attributeId) : null;
                                $isColor = $attribute && $attribute->type === 'color';
                                $isString = $attribute && in_array($attribute->type, ['string', 'text'], true);

                                $fields = [
                                    TextInput::make('value')
                                        ->label('Название значения')
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                                    TextInput::make('slug')
                                        ->label('Слаг')
                                        ->helperText('Оставьте пустым для автоматической генерации'),
                                ];

                                if ($isColor) {
                                    $fields[] = ColorPicker::make('color_code')
                                        ->label('HEX цвета')
                                        ->helperText('Цвет чипа на карточке товара');
                                }

                                if ($isString || !$isColor) {
                                    $fields[] = TextInput::make('sort_order')
                                        ->label('Порядок')
                                        ->numeric()
                                        ->default(0);
                                }

                                return $fields;
                            })
                            ->createOptionUsing(function (array $data, $get): int {
                                $attributeId = $get('attribute_id');
                                $data['attribute_id'] = $attributeId;
                                $value = AttributeValue::create($data);
                                return $value->id;
                            }),

                        TextInput::make('custom_value')
                            ->label('Значение (ручной ввод)')
                            ->maxLength(500)
                            ->visible(function ($get) {
                                $attributeId = $get('attribute_id');
                                if (!$attributeId) return false;
                                $attribute = Attribute::find($attributeId);
                                return $attribute && $attribute->allow_custom_value
                                    && in_array($attribute->type, ['string', 'text', 'number_input'], true);
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state !== null && $state !== '') {
                                    $set('attribute_value_id', null);
                                }
                            })
                            ->required(function ($get) {
                                $attributeId = $get('attribute_id');
                                if (!$attributeId) return false;
                                $attribute = Attribute::find($attributeId);
                                return ($attribute->is_required ?? false) && empty($get('attribute_value_id'));
                            }),
                    ])
                    ->columns(1)
                    ->compact()
                    ->defaultItems(0)
                    ->addActionLabel('Добавить характеристику')
                    ->collapsible()
                    ->itemLabel(fn(array $state): ?string => 
                        ($attribute = Attribute::find($state['attribute_id'] ?? null))
                            ? $attribute->name . 
                            (isset($state['attribute_value_id']) && !empty($state['attribute_value_id'])
                                ? (is_array($state['attribute_value_id'])
                                    ? ' (несколько значений)'
                                    : (($value = AttributeValue::find($state['attribute_value_id']))
                                        ? ': ' . $value->value
                                        : ''))
                                : '')
                            : 'Новая характеристика'
                    )
                    ->helperText('Выберите характеристику и её значение. Для добавления новых значений используйте раздел "Характеристики" в меню.')
                    ->columnSpanFull(),
            ]);
    }

    protected static function colorSection(): Section
    {
        return Section::make('Цвет')
                    ->description('Для невариативных товаров: цвет для фильтров каталога. У вариативных товаров цвет задаётся в вариациях.')
                    ->schema([
                        TextInput::make('color')
                            ->label(__('filament/admin_sv/product_resource.color'))
                            ->maxLength(255)
                            ->helperText('Название цвета для фильтров (например: Серый, Синий)'),

                        TextInput::make('color_code')
                            ->label(__('filament/admin_sv/product_resource.color_code'))
                            ->maxLength(7)
                            ->placeholder('#808080')
                            ->helperText('HEX код цвета для отображения на сайте'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn($record) => !$record || !$record->is_variable);
    }

    protected static function dimensionsSection(): Section
    {
        return Section::make('Размеры и вес')
                    ->description('Физические параметры товара для расчета доставки')
                    ->schema([
                        TextInput::make('length')
                            ->label(__('filament/admin_sv/product_resource.length'))
                            ->numeric()
                            ->suffix('см')
                            ->helperText('Длина товара в сантиметрах'),

                        TextInput::make('width')
                            ->label(__('filament/admin_sv/product_resource.width'))
                            ->numeric()
                            ->suffix('см')
                            ->helperText('Ширина товара в сантиметрах'),

                        TextInput::make('height')
                            ->label(__('filament/admin_sv/product_resource.height'))
                            ->numeric()
                            ->suffix('см')
                            ->helperText('Высота товара в сантиметрах'),

                        TextInput::make('weight')
                            ->label(__('filament/admin_sv/product_resource.weight'))
                            ->numeric()
                            ->suffix('кг')
                            ->helperText('Вес товара в килограммах'),
                    ])
                    ->columns(4)
                    ->collapsible()
                    ->collapsed();
    }

    protected static function taxShippingSection(): Section
    {
        return Section::make('Налоги и доставка')
                    ->description('Категории для расчёта налогов и стоимости доставки')
                    ->schema([
                        Select::make('tax_category_id')
                            ->label(__('filament/admin_sv/product_resource.tax_category_id'))
                            ->options(TaxCategory::all()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Категория для расчета налогов'),

                        Select::make('shipping_category_id')
                            ->label(__('filament/admin_sv/product_resource.shipping_category_id'))
                            ->options(ModelsShippingCategory::all()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Категория для расчета стоимости доставки'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed();
    }

    protected static function importSection(): Section
    {
        return Section::make('Импорт 1С')
                    ->description('Идентификаторы для сопоставления при импорте каталога')
                    ->schema([
                        TextInput::make('external_id')
                            ->label('Внешний ID 1С (external_id)')
                            ->maxLength(255)
                            ->helperText('UUID товара/вариации в кэше 1С. Нужен для загрузки остатков по складам (GET …/products/{id}/stocks). У вариаций — свой ID в карточке вариации.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed();
    }

    protected static function seoSection(): Section
    {
        return Section::make('SEO настройки')
                    ->description('Мета-теги для поисковых систем')
                    ->schema([
                        SEO::make()
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed();
    }
}
