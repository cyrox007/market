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
use App\Services\Catalog\OneCProductSyncService;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Facades\Filament;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        // ProductResource uses ProductTabbedForm directly. Keep this legacy entrypoint
        // delegated to the same schema so the old two-column form with duplicate color
        // fields can never be reintroduced by another caller accidentally.
        return ProductTabbedForm::configure($schema);
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
                    ->afterStateUpdated(fn($state, $set, $get) => $get('slug') === '' || $get('slug') === null ? $set('slug', Str::slug($state)) : null)
                    ->helperText('Полное название товара для отображения на сайте')
                    ->columnSpanFull(),

                Section::make('Адрес на сайте (ЧПУ)')
                    ->description('Формируется автоматически из названия')
                    ->extraAttributes(['class' => 'chpu-section'])
                    ->schema([
                        TextInput::make('slug')
                            ->label(__('filament/admin_sv/product_resource.slug'))
                            ->maxLength(255)
                            ->unique(Product::class, 'slug', ignoreRecord: true)
                            ->helperText('Адрес карточки на сайте: /product/{ЧПУ}. Генерируется из названия, можно изменить.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),

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
            ->visible(fn() => ! ProductStockSettings::getInstance()->warehouse_accounting_enabled)
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
            ->collapsed(fn($record) => $record?->is_variable)
            ->visible(fn() => ProductStockSettings::getInstance()->warehouse_accounting_enabled)
            ->schema([
                // Для вариативных товаров – сводка по складам
                \Filament\Forms\Components\Placeholder::make('variants_warehouse_summary')
                    ->label('Суммарные остатки по складам (все вариации)')
                    ->content(function ($record) {
                        if (!$record || !$record->is_variable) {
                            return '—';
                        }
                        $stocks = [];
                        foreach ($record->variants as $variant) {
                            foreach ($variant->warehouseStocks as $ws) {
                                $wid = $ws->warehouse_id;
                                if (!isset($stocks[$wid])) {
                                    $stocks[$wid] = [
                                        'name' => $ws->warehouse->name ?? 'Склад #' . $wid,
                                        'total' => 0,
                                    ];
                                }
                                $stocks[$wid]['total'] += $ws->quantity;
                            }
                        }
                        if (empty($stocks)) {
                            return 'Нет остатков на складах';
                        }
                        // Выводим в виде HTML с ссылкой на вкладку вариаций
                        $list = collect($stocks)
                            ->map(fn($item) => e($item['name']) . ': ' . $item['total'])
                            ->implode('<br>');
                        $url = route('filament.admin_sv.resources.products.edit', [
                            'record' => $record->id,
                            'tab' => 'productVariantsRelationManager'
                        ]);
                        $link = '<br><br><a href="' . e($url) . '" class="text-primary-600 hover:underline">Перейти к редактированию остатков вариаций →</a>';
                        return new \Illuminate\Support\HtmlString($list . $link);
                    })
                    ->visible(fn($record) => $record && $record->is_variable)
                    ->columnSpanFull(),

                Toggle::make('backorder')
                    ->label(__('filament/admin_sv/product_resource.backorder'))
                    ->helperText('Если товара нет в наличии, разрешить клиентам делать предзаказ')
                    ->default(false)
                    ->columnSpanFull(),
                // Для простых товаров – Repeater для редактирования
                WarehouseStocksFormComponents::warehouseStocksRepeater()
                    ->visible(fn($record) => !$record || !$record->is_variable)
                    ->helperText('Склады создаются при синхронизации из 1С. Остатки по каждому складу обновляются автоматически.'),
            ]);
    }

    protected static function imagesSection(): Section
    {
        return Section::make('Изображения товара')
            ->description('Загрузка главного изображения и галереи')
            ->schema([
                SpatieMediaLibraryFileUpload::make('main_image')
                    ->label('Главное изображение')
                    // Имя коллекции задаёт модель: главное фото читается из images.
                    ->collection('images')
                    ->image()
                    ->imageEditor()
                    ->imageCropAspectRatio('1:1')
                    ->maxSize(10240)
                    ->helperText('Главное фото на карточке товара и в списках. Рекомендуется 800x800px, до 10MB.')
                    ->columnSpanFull(),

                SpatieMediaLibraryFileUpload::make('gallery')
                    ->label(__('filament/admin_sv/product_resource.images'))
                    ->collection('gallery')
                    ->multiple()
                    ->reorderable()
                    ->image()
                    ->imageEditor()
                    ->maxFiles(20)
                    ->maxSize(10240)
                    ->helperText('Дополнительные изображения товара. Можно менять порядок перетаскиванием. Максимум 20 файлов по 10MB')
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    protected static function dimensionsSection(): Section
    {
        return Section::make('Габариты и вес для доставки')
            ->description('Физические параметры упаковки/товара для расчёта доставки. Это не коммерческий размер вариации.')
            ->schema([
                TextInput::make('length')
                    ->label(__('filament/admin_sv/product_resource.length'))
                    ->numeric()
                    ->suffix('см')
                    ->minValue(0)
                    ->step(0.01)
                    ->helperText('Длина в сантиметрах'),

                TextInput::make('width')
                    ->label(__('filament/admin_sv/product_resource.width'))
                    ->numeric()
                    ->suffix('см')
                    ->minValue(0)
                    ->step(0.01)
                    ->helperText('Ширина в сантиметрах'),

                TextInput::make('height')
                    ->label(__('filament/admin_sv/product_resource.height'))
                    ->numeric()
                    ->suffix('см')
                    ->minValue(0)
                    ->step(0.01)
                    ->helperText('Высота в сантиметрах'),

                TextInput::make('weight')
                    ->label(__('filament/admin_sv/product_resource.weight'))
                    ->numeric()
                    ->suffix('кг')
                    ->minValue(0)
                    ->step(0.01)
                    ->helperText('Вес в килограммах'),
            ])
            ->columns(2);
    }

    protected static function colorSection(): Section
    {
        return Section::make('Цвет товара')
            ->description('Цвет для обычного (не вариативного) товара')
            ->visible(fn($record) => !$record || !$record->is_variable)
            ->schema([
                TextInput::make('color')
                    ->label('Название цвета')
                    ->maxLength(100)
                    ->helperText('Например: Серый, Белый, Дуб сонома'),

                ColorPicker::make('color_code')
                    ->label('Код цвета')
                    ->helperText('HEX-код цвета для визуального отображения (опционально)'),
            ])
            ->columns(2);
    }

    protected static function seoSection(): Section
    {
        return Section::make('SEO')
            ->description('Поисковая оптимизация товара')
            ->schema([
                SEO::make('seo')
                    ->hiddenLabel()
                    ->columnSpanFull(),
            ])
            ->columns(1)
            ->collapsible()
            ->collapsed();
    }

    protected static function taxShippingSection(): Section
    {
        return Section::make('Налоги и доставка')
            ->description('Категории налогообложения и доставки')
            ->schema([
                Select::make('tax_category_id')
                    ->label(__('filament/admin_sv/product_resource.tax_category'))
                    ->options(TaxCategory::all()->pluck('name', 'id'))
                    ->searchable()
                    ->helperText('Категория для расчета налогов (если применяется)')
                    ->columnSpanFull(),

                Select::make('shipping_category_id')
                    ->label(__('filament/admin_sv/product_resource.shipping_category'))
                    ->options(ModelsShippingCategory::all()->pluck('name', 'id'))
                    ->searchable()
                    ->helperText('Категория для расчета стоимости доставки')
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    protected static function importSection(): Section
    {
        return Section::make('Импорт 1С')
            ->description('Данные для синхронизации с 1С')
            ->schema([
                TextInput::make('external_id')
                    ->label(__('filament/admin_sv/product_resource.external_id'))
                    ->maxLength(255)
                    ->disabled()
                    ->dehydrated(true)
                    ->helperText('Внешний ID товара в 1С (только для чтения)'),

                Action::make('load_from_1c')
                    ->label('Загрузить данные из 1С')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Загрузка данных из 1С')
                    ->modalDescription('Данные товара будут загружены из 1С и заменят текущие значения. Продолжить?')
                    ->action(function ($record, $livewire) {
                        if (!$record || !$record->exists) {
                            Notification::make()
                                ->title('Ошибка')
                                ->body('Сначала сохраните товар')
                                ->danger()
                                ->send();
                            return;
                        }

                        if (!$record->external_id) {
                            Notification::make()
                                ->title('Ошибка')
                                ->body('У товара не указан внешний ID для 1С')
                                ->danger()
                                ->send();
                            return;
                        }

                        try {
                            $syncService = app(OneCProductSyncService::class);
                            $result = $syncService->syncProductFrom1C($record);

                            if ($result['success']) {
                                Notification::make()
                                    ->title('Данные загружены')
                                    ->body($result['message'])
                                    ->success()
                                    ->send();

                                // Перенаправляем для обновления формы
                                $livewire->redirect($livewire->getResource()::getUrl('edit', ['record' => $record]));
                            } else {
                                Notification::make()
                                    ->title('Ошибка загрузки')
                                    ->body($result['message'])
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Ошибка')
                                ->body('Произошла ошибка при загрузке данных: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn($record) => $record && $record->exists && $record->external_id),
            ])
            ->columns(1)
            ->collapsible()
            ->collapsed();
    }

    protected static function attributesSection(): Section
    {
        return Section::make('Характеристики товара')
            ->description('Материал, стиль, производитель и другие характеристики')
            ->schema([
                Repeater::make('product_attributes')
                    ->label('Характеристики')
                    ->schema([
                        Select::make('attribute_id')
                            ->label('Характеристика')
                            ->options(Attribute::orderBy('sort_order')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                $set('attribute_value_id', null);
                                $set('custom_value', null);
                            })
                            ->createOptionForm([
                                TextInput::make('name')->label('Название')->required()->maxLength(255),
                                Select::make('type')
                                    ->label('Тип')
                                    ->options([
                                        'text' => 'Текст',
                                        'select' => 'Список',
                                        'color' => 'Цвет',
                                        'number' => 'Число',
                                    ])
                                    ->default('text')
                                    ->required(),
                                Toggle::make('is_use_in_variations')->label('Участвует в вариациях')->default(false),
                                Toggle::make('is_filterable')->label('Показывать в фильтрах')->default(false),
                                Toggle::make('is_multiple')->label('Множественный выбор')->default(false),
                                Toggle::make('allow_custom_value')->label('Разрешить ручной ввод')->default(false),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $slug = Str::slug($data['name']);
                                $base = $slug;
                                $i = 2;
                                while (Attribute::where('slug', $slug)->exists()) {
                                    $slug = $base . '-' . $i++;
                                }
                                return Attribute::create([
                                    'name' => $data['name'],
                                    'slug' => $slug,
                                    'type' => $data['type'] ?? 'text',
                                    'is_use_in_variations' => (bool) ($data['is_use_in_variations'] ?? false),
                                    'is_filterable' => (bool) ($data['is_filterable'] ?? false),
                                    'is_multiple' => (bool) ($data['is_multiple'] ?? false),
                                    'allow_custom_value' => (bool) ($data['allow_custom_value'] ?? false),
                                ])->id;
                            }),

                        Select::make('attribute_value_id')
                            ->label('Значение')
                            ->options(function ($get) {
                                $attributeId = $get('attribute_id');
                                if (!$attributeId) {
                                    return [];
                                }
                                $attr = Attribute::find($attributeId);
                                if (!$attr) {
                                    return [];
                                }
                                return $attr->values()->orderBy('sort_order')->pluck('value', 'id');
                            })
                            ->multiple(function ($get) {
                                $attributeId = $get('attribute_id');
                                if (!$attributeId) {
                                    return false;
                                }
                                return (bool) Attribute::find($attributeId)?->is_multiple;
                            })
                            ->searchable()
                            ->live()
                            ->disabled(fn($get) => !$get('attribute_id'))
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state !== null && $state !== '' && $state !== []) {
                                    $set('custom_value', null);
                                }
                            })
                            ->createOptionForm([
                                TextInput::make('value')->label('Значение')->required()->maxLength(255),
                                ColorPicker::make('color_code')
                                    ->label('Цвет')
                                    ->visible(function ($get) {
                                        $attrId = $get('../../attribute_id');
                                        return $attrId && Attribute::find($attrId)?->type === 'color';
                                    }),
                            ])
                            ->createOptionUsing(function (array $data, $get) {
                                $attributeId = $get('attribute_id');
                                if (!$attributeId) {
                                    return null;
                                }
                                $slug = Str::slug($data['value']);
                                $base = $slug;
                                $i = 2;
                                while (AttributeValue::where('attribute_id', $attributeId)->where('slug', $slug)->exists()) {
                                    $slug = $base . '-' . $i++;
                                }
                                return AttributeValue::create([
                                    'attribute_id' => $attributeId,
                                    'value' => $data['value'],
                                    'slug' => $slug,
                                    'color_code' => $data['color_code'] ?? null,
                                ])->id;
                            }),

                        TextInput::make('custom_value')
                            ->label('Своё значение')
                            ->maxLength(1000)
                            ->visible(function ($get) {
                                $attributeId = $get('attribute_id');
                                if (!$attributeId) {
                                    return false;
                                }
                                return (bool) Attribute::find($attributeId)?->allow_custom_value;
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state !== null && trim((string) $state) !== '') {
                                    $set('attribute_value_id', null);
                                }
                            }),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->defaultItems(0)
                    ->addActionLabel('Добавить характеристику')
                    ->itemLabel(function (array $state): ?string {
                        $attr = Attribute::find($state['attribute_id'] ?? null);
                        if (!$attr) {
                            return 'Характеристика';
                        }
                        $valueId = $state['attribute_value_id'] ?? null;
                        if (is_array($valueId)) {
                            $values = AttributeValue::whereIn('id', $valueId)->pluck('value')->all();
                            $valueLabel = $values ? implode(', ', $values) : null;
                        } else {
                            $value = $valueId ? AttributeValue::find($valueId) : null;
                            $valueLabel = $value?->value;
                        }
                        $custom = trim((string) ($state['custom_value'] ?? ''));
                        return $attr->name . ($valueLabel ? ': ' . $valueLabel : ($custom !== '' ? ': ' . $custom : ''));
                    })
                    ->columnSpanFull(),
            ])
            ->collapsible()
            ->collapsed(false);
    }
}
