<?php

namespace App\Filament\Resources\Shipping\ShippingLocations\Schemas;

use App\Models\Shipping\ShippingLocation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Vanilo\Shipment\Models\ShippingMethod;

class ShippingLocationForm
{
    /**
     * Секции «Сборка», «Ограничения», «Vanilo» свернуты по умолчанию — меньше визуального шума для администратора.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        Select::make('parent_id')
                            ->label(__('filament/admin_sv/shipping_location_resource.parent_id'))
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Выберите родительскую локацию (федеральный округ или регион). Оставьте пустым для федеральных округов.'),

                        TextInput::make('name')
                            ->label(__('filament/admin_sv/shipping_location_resource.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set) {
                                if (!$state) {
                                    return;
                                }
                                $set('slug', \Str::slug($state));
                            }),

                        TextInput::make('slug')
                            ->label(__('filament/admin_sv/shipping_location_resource.slug'))
                            ->required()
                            ->maxLength(255)
                            ->helperText('Уникальный идентификатор для URL'),

                        Select::make('type')
                            ->label(__('filament/admin_sv/shipping_location_resource.type'))
                            ->options(['federal_district' => __('filament/admin_sv/shipping_location_resource.type.federal_district'), 'region' => __('filament/admin_sv/shipping_location_resource.type.region'), 'locality' => __('filament/admin_sv/shipping_location_resource.type.locality')])
                            ->required()
                            ->default('federal_district')
                            ->helperText('Тип локации в иерархии доставки'),

                        Select::make('location_type')
                            ->label(__('filament/admin_sv/shipping_location_resource.location_type'))
                            ->options(['federal_district' => __('filament/admin_sv/shipping_location_resource.location_type.federal_district'), 'republic' => __('filament/admin_sv/shipping_location_resource.location_type.republic'), 'oblast' => __('filament/admin_sv/shipping_location_resource.location_type.oblast'), 'krai' => __('filament/admin_sv/shipping_location_resource.location_type.krai'), 'autonomous_okrug' => __('filament/admin_sv/shipping_location_resource.location_type.autonomous_okrug'), 'federal_city' => __('filament/admin_sv/shipping_location_resource.location_type.federal_city'), 'city' => __('filament/admin_sv/shipping_location_resource.location_type.city'), 'town' => __('filament/admin_sv/shipping_location_resource.location_type.town'), 'village' => __('filament/admin_sv/shipping_location_resource.location_type.village'), 'urban_settlement' => __('filament/admin_sv/shipping_location_resource.location_type.urban_settlement'), 'district' => __('filament/admin_sv/shipping_location_resource.location_type.district')])
                            ->helperText('Тип географической единицы'),

                        TextInput::make('code')
                            ->label(__('filament/admin_sv/shipping_location_resource.code'))
                            ->maxLength(10)
                            ->helperText('Код локации (например, код субъекта РФ)'),

                        TextInput::make('postal_code')
                            ->label(__('filament/admin_sv/shipping_location_resource.postal_code'))
                            ->maxLength(10)
                            ->helperText('Почтовый индекс (для населенных пунктов)'),

                        TextInput::make('sort_order')
                            ->label(__('filament/admin_sv/shipping_location_resource.sort_order'))
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->label(__('filament/admin_sv/shipping_location_resource.is_active'))
                            ->default(true),
                    ])->columns(2),

                Section::make('Самовывоз')
                    ->description('Текст для покупателя на странице оформления заказа при выборе самовывоза (адрес пункта, режим работы и т.д.)')
                    ->schema([
                        Select::make('pickup_enabled')
                            ->label(__('filament/admin_sv/shipping_location_resource.pickup_enabled'))
                            ->options([
                                '1' => 'Да',
                                '0' => 'Нет',
                            ])
                            ->placeholder('Наследовать от родительской локации')
                            ->helperText('Если не выбрано, значение наследуется от родительской локации'),
                        Textarea::make('pickup_notice')
                            ->label(__('filament/admin_sv/shipping_location_resource.pickup_notice'))
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Стоимость и сроки доставки (без привязанных служб)')
                    ->description('Используются только если для локации не заданы службы доставки во вкладке «Службы доставки». Если службы указаны, цена и срок берутся из каждой службы.')
                    ->schema([
                        TextInput::make('delivery_price')
                            ->label(__('filament/admin_sv/shipping_location_resource.delivery_price'))
                            ->numeric()
                            ->prefix('₽')
                            ->helperText('Стоимость доставки (наследуется дочерними локациями, если не указана)'),

                        TextInput::make('free_delivery_threshold')
                            ->label(__('filament/admin_sv/shipping_location_resource.free_delivery_threshold'))
                            ->numeric()
                            ->prefix('₽'),

                        TextInput::make('delivery_days_min')
                            ->label(__('filament/admin_sv/shipping_location_resource.delivery_days_min'))
                            ->numeric()
                            ->minValue(1),

                        TextInput::make('delivery_days_max')
                            ->label(__('filament/admin_sv/shipping_location_resource.delivery_days_max'))
                            ->numeric()
                            ->minValue(1),
                    ])->columns(2),

                Section::make('Сборка мебели')
                    ->description('Если не указаны, наследуются от родительской локации')
                    ->collapsed()
                    ->schema([
                        Toggle::make('requires_assembly')
                            ->label(__('filament/admin_sv/shipping_location_resource.requires_assembly'))
                            ->default(false),

                        TextInput::make('assembly_price')
                            ->label(__('filament/admin_sv/shipping_location_resource.assembly_price'))
                            ->numeric()
                            ->prefix('₽'),

                        TextInput::make('assembly_days')
                            ->label(__('filament/admin_sv/shipping_location_resource.assembly_days'))
                            ->numeric()
                            ->minValue(1),
                    ])->columns(3),

                Section::make('Ограничения доставки')
                    ->description('Если не указаны, наследуются от родительской локации')
                    ->collapsed()
                    ->schema([
                        TextInput::make('min_order_amount')
                            ->label(__('filament/admin_sv/shipping_location_resource.min_order_amount'))
                            ->numeric()
                            ->prefix('₽'),

                        TextInput::make('max_order_weight')
                            ->label(__('filament/admin_sv/shipping_location_resource.max_order_weight'))
                            ->numeric()
                            ->minValue(0),

                        TextInput::make('max_order_volume')
                            ->label(__('filament/admin_sv/shipping_location_resource.max_order_volume'))
                            ->numeric()
                            ->minValue(0),
                    ])->columns(3),

                Section::make('Интеграция с Vanilo Shipping')
                    ->collapsed()
                    ->schema([
                        Select::make('shipping_method_id')
                            ->label(__('filament/admin_sv/shipping_location_resource.shipping_method_id'))
                            ->relationship('shippingMethod', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Метод доставки из Vanilo Shipping (наследуется дочерними локациями, если не указан)'),
                    ]),
            ]);
    }
}
