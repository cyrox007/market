<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RalphJSmit\Filament\SEO\SEO;

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
                            self::stockSection(),
                            self::warehouseStocksSection(),
                            self::dimensionsSection(),
                            self::seoSection(),
                        ]),

                        Group::make([
                            self::categoriesSection(),
                            self::statusPriceSection(),
                            self::imagesSection(),
                            self::taxShippingSection(),
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

    protected static function attributesSection(): Section
    {
        return Section::make('Характеристики товара')
            ->description('Цвет, коммерческий размер, материал и другие свойства. Структура характеристик настраивается в отдельном разделе.')
            ->schema([
                Repeater::make('product_attributes')
                    ->label('Характеристики')
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
                    ->defaultItems(0)
                    ->addActionLabel('Добавить характеристику')
                    ->itemLabel(function (array $state): ?string {
                        $attribute = Attribute::query()->find($state['attribute_id'] ?? null);
                        if (! $attribute) {
                            return 'Характеристика';
                        }

                        $valueId = $state['attribute_value_id'] ?? null;
                        if (is_array($valueId)) {
                            $values = AttributeValue::query()->whereIn('id', $valueId)->pluck('value')->all();
                            $valueLabel = $values ? implode(', ', $values) : null;
                        } else {
                            $value = $valueId ? AttributeValue::query()->find($valueId) : null;
                            $valueLabel = $value?->value;
                        }

                        $custom = trim((string) ($state['custom_value'] ?? ''));

                        return $attribute->name . ($valueLabel ? ': ' . $valueLabel : ($custom !== '' ? ': ' . $custom : ''));
                    })
                    ->columnSpanFull(),
            ])
            ->collapsible()
            ->collapsed(false);
    }
}
