<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use RalphJSmit\Filament\SEO\SEO;
use Vanilo\Product\Models\ProductState;

class ProductClassicForm extends ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Group::make([
                            self::mainInfoSection(),
                            self::categoriesSection(),
                            self::pricesSection(),
                            self::statusSection(),
                            self::stockSection(),
                            self::warehouseStocksSection(),
                        ]),

                        Group::make([
                            self::imagesSection(),
                            self::dimensionsSection(),
                            self::taxShippingSection(),
                            self::seoSection(),
                            self::importSection(),
                        ]),
                    ])
                    ->columnSpanFull(),

                self::attributesSection()->columnSpanFull(),
            ]);
    }

    protected static function seoSection(): Section
    {
        return Section::make('SEO')
            ->description('Поисковая оптимизация товара')
            ->schema([
                SEO::make()
                    ->columnSpanFull(),
            ])
            ->columns(1)
            ->collapsible()
            ->collapsed();
    }

    protected static function pricesSection(): Section
    {
        return Section::make('Цены')
            ->description('Цена продажи и цена до скидки')
            ->schema([
                TextInput::make('price')
                    ->label(__('filament/admin_sv/product_resource.price'))
                    ->numeric()
                    ->prefix('₽')
                    ->required()
                    ->helperText('Цена продажи. Показывается на карточке товара и в заказе.'),

                TextInput::make('original_price')
                    ->label(__('filament/admin_sv/product_resource.original_price'))
                    ->numeric()
                    ->prefix('₽')
                    ->helperText('Старая цена — отображается зачёркнутой рядом с ценой продажи.'),
            ])
            ->columns(2);
    }

    protected static function statusSection(): Section
    {
        return Section::make('Статус и вариативность')
            ->description('Видимость товара, приоритет и наличие торговых предложений')
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

                Toggle::make('is_variable')
                    ->label(__('filament/admin_sv/product_resource.is_variable'))
                    ->helperText(function ($record) {
                        if (! $record) {
                            return 'Вариации (цвет, размер) — во вкладке «Торговые предложения» после сохранения.';
                        }
                        $variantsCount = $record->variants()->count();
                        if ($variantsCount > 0) {
                            return "У товара {$variantsCount} вариаций. Удалите все, чтобы отключить.";
                        }
                        return 'Вариации добавляются во вкладке «Торговые предложения».';
                    })
                    ->default(false)
                    ->disabled(fn ($record) => $record && $record->variants()->count() > 0)
                    ->dehydrated()
                    ->columnSpanFull(),

                Placeholder::make('variants_info')
                    ->label('Торговые предложения')
                    ->content(function ($record) {
                        if (! $record) {
                            return '—';
                        }
                        $variantsCount = $record->variants()->count();
                        return $variantsCount === 0
                            ? 'Нет. Добавьте во вкладке «Торговые предложения».'
                            : "{$variantsCount} шт. Управление во вкладке «Торговые предложения».";
                    })
                    ->visible(fn ($record) => $record !== null)
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    protected static function attributesSection(): Section
    {
        return Section::make('Характеристики товара')
            ->description('Цвет, коммерческий размер, материал и другие свойства. Структура характеристик настраивается в отдельном разделе.')
            ->schema([
                Repeater::make('product_attributes')
                    ->label('Характеристики')
                    ->table([
                        TableColumn::make('Характеристика')->markAsRequired(),
                        TableColumn::make('Значение'),
                        TableColumn::make('Своё значение'),
                    ])
                    ->schema([
                        Select::make('attribute_id')
                            ->label('Характеристика')
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
                                if (! $attributeId) {
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
                                return $attributeId && (bool) Attribute::query()->find($attributeId)?->allow_custom_value;
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state !== null && trim((string) $state) !== '') {
                                    $set('attribute_value_id', null);
                                }
                            }),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel('Добавить характеристику')
                    ->columnSpanFull(),
            ])
            ->collapsible()
            ->collapsed(false);
    }
}
