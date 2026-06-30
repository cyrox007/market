<?php

namespace App\Filament\Resources\Shipping\DeliveryHandlingTypes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeliveryHandlingTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament/admin_sv/delivery_handling_type_resource.name'))
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
                            ->label(__('filament/admin_sv/delivery_handling_type_resource.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Уникальный идентификатор для URL'),

                        TextInput::make('code')
                            ->label(__('filament/admin_sv/delivery_handling_type_resource.code'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Уникальный код типа (например, elevator, manual_lift)'),

                        Textarea::make('description')
                            ->label(__('filament/admin_sv/delivery_handling_type_resource.description'))
                            ->rows(3)
                            ->helperText('Описание типа обработки доставки'),

                        TextInput::make('sort_order')
                            ->label(__('filament/admin_sv/delivery_handling_type_resource.sort_order'))
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->label(__('filament/admin_sv/delivery_handling_type_resource.is_active'))
                            ->default(true),
                    ])->columns(2),

                Section::make('Параметры обработки')
                    ->schema([
                        Toggle::make('requires_floor')
                            ->label(__('filament/admin_sv/delivery_handling_type_resource.requires_floor'))
                            ->default(false)
                            ->helperText('Требуется ли указание этажа для данного типа обработки'),

                        TextInput::make('max_floor')
                            ->label(__('filament/admin_sv/delivery_handling_type_resource.max_floor'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->helperText('Максимальный этаж для данного типа обработки'),

                        Toggle::make('requires_elevator')
                            ->label(__('filament/admin_sv/delivery_handling_type_resource.requires_elevator'))
                            ->default(false)
                            ->helperText('Требуется ли наличие лифта для данного типа обработки'),
                    ])->columns(3),
            ]);
    }
}
