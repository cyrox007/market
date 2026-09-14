<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Manufacturer;
use App\Models\Product\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Vanilo\Product\Models\ProductState;

class ProductOperatorPocForm extends ProductClassicForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 2])
                ->schema([
                    self::operatorMainSection(),
                    self::operatorSalesSection(),
                ])
                ->extraAttributes(['id' => 'product-main', 'class' => 'scroll-mt-28'])
                ->columnSpanFull(),

            self::operatorDescriptionSection()
                ->extraAttributes(['id' => 'product-description', 'class' => 'scroll-mt-28'])
                ->columnSpanFull(),

            self::operatorAttributesSection()
                ->extraAttributes(['id' => 'product-attributes', 'class' => 'scroll-mt-28'])
                ->columnSpanFull(),

            View::make('filament.resources.products.components.product-variants-workspace')
                ->hidden(fn (?Product $record): bool => $record === null || $record->isVariant())
                ->columnSpanFull(),

            Section::make('Остатки')
                ->description('Складские остатки и предзаказ')
                ->schema([
                    self::stockSection(),
                    self::warehouseStocksSection(),
                ])
                ->collapsible()
                ->collapsed()
                ->extraAttributes(['id' => 'product-stock', 'class' => 'scroll-mt-28'])
                ->columnSpanFull(),

            self::imagesSection()
                ->extraAttributes(['id' => 'product-media', 'class' => 'scroll-mt-28'])
                ->columnSpanFull(),

            View::make('filament.resources.products.components.product-related-workspaces')
                ->hidden(fn (?Product $record): bool => $record === null)
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
                ->extraAttributes(['id' => 'product-extra', 'class' => 'scroll-mt-28'])
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
                        TextInput::make('sku')->label('Артикул')->maxLength(255),
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
                        TextInput::make('price')->label('Цена')->numeric()->prefix('₽')->required(),
                        TextInput::make('original_price')->label('Старая цена')->numeric()->prefix('₽'),
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
                    ->rows(5)
                    ->columnSpanFull(),
            ]);
    }

    protected static function operatorAttributesSection(): Section
    {
        return Section::make('Характеристики')
            ->description('В обычном режиме видны только название и текущее значение. Разверните строку, чтобы отредактировать её.')
            ->schema([
                Repeater::make('product_attributes')
                    ->hiddenLabel()
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
                    ->columns(3)
                    ->collapsible()
                    ->collapsed()
                    ->compact()
                    ->reorderable()
                    ->defaultItems(0)
                    ->addActionLabel('Добавить характеристику')
                    ->itemLabel(function (array $state): ?string {
                        $attribute = Attribute::query()->find($state['attribute_id'] ?? null);
                        if (! $attribute) {
                            return 'Новая характеристика';
                        }

                        $valueId = $state['attribute_value_id'] ?? null;
                        if (is_array($valueId)) {
                            $values = AttributeValue::query()->whereIn('id', $valueId)->pluck('value')->all();
                            $valueLabel = $values ? implode(', ', $values) : null;
                        } else {
                            $valueLabel = $valueId ? AttributeValue::query()->find($valueId)?->value : null;
                        }

                        $custom = trim((string) ($state['custom_value'] ?? ''));
                        $displayValue = $valueLabel ?: ($custom !== '' ? $custom : '—');

                        return $attribute->name . ' · ' . $displayValue;
                    })
                    ->columnSpanFull(),
            ]);
    }

    protected static function technicalSection(): Section
    {
        return Section::make('Служебные данные')
            ->schema([
                TextInput::make('slug')->label('ЧПУ')->maxLength(255)->unique(Product::class, 'slug', ignoreRecord: true),
                TextInput::make('gtin')->label('GTIN')->maxLength(255),
                TextInput::make('priority')->label('Приоритет')->numeric()->default(0),
            ])
            ->columns(3)
            ->collapsible()
            ->collapsed();
    }
}
