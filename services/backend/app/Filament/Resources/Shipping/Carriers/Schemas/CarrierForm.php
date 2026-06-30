<?php

namespace App\Filament\Resources\Shipping\Carriers\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CarrierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament/admin_sv/carrier_resource.name'))
                            ->required()
                            ->maxLength(255)
                            ->helperText('Например: DHL, СДЭК, Почта России'),

                        Textarea::make('description')
                            ->label(__('filament/admin_sv/carrier_resource.description'))
                            ->rows(3)
                            ->helperText('Описание службы доставки и её особенностей'),

                        Toggle::make('is_active')
                            ->label(__('filament/admin_sv/carrier_resource.is_active'))
                            ->default(true)
                            ->helperText('Активна ли служба доставки для использования'),
                    ])->columns(1),

                Section::make('Конфигурация')
                    ->schema([
                        KeyValue::make('configuration')
                            ->label(__('filament/admin_sv/carrier_resource.configuration'))
                            ->keyLabel('Ключ')
                            ->valueLabel('Значение')
                            ->helperText('Дополнительные настройки службы доставки (API ключи, параметры и т.д.)'),
                    ]),
            ]);
    }
}
