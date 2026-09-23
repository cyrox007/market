<?php

namespace App\Filament\Resources\Payment\PaymentMethods\Schemas;

use App\Filament\Support\LucideIconSelect;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('code')
                            ->label('Код метода оплаты')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Уникальный код метода (например, card, cash, installment)'),

                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Название метода оплаты для отображения'),

                        Textarea::make('description')
                            ->label('Описание')
                            ->rows(3)
                            ->helperText('Описание метода оплаты'),

                        LucideIconSelect::make('icon')
                            ->label('Иконка')
                            ->helperText('Выберите иконку Lucide, например credit-card.'),

                        TextInput::make('gateway')
                            ->label('Gateway')
                            ->default('manual')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Тип платежного шлюза (manual для ручной обработки)'),

                        \Filament\Forms\Components\KeyValue::make('configuration')
                            ->label('Конфигурация')
                            ->default([])
                            ->helperText('Дополнительные настройки метода оплаты'),

                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0)
                            ->helperText('Порядок отображения в списке'),

                        Toggle::make('is_active')
                            ->label('Активен на сайте')
                            ->default(true)
                            ->helperText('Если выключено — метод не показывается в checkout и недоступен для новых заказов. Для отключения в отдельном регионе используйте настройки локации доставки.'),

                        Toggle::make('is_enabled')
                            ->label('Включен (системный)')
                            ->default(true)
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Синхронизируется с «Активен на сайте» автоматически.'),
                    ])->columns(2),
            ]);
    }
}
