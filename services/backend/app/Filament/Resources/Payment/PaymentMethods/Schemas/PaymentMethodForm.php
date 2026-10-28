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

                        Toggle::make('is_coming_soon')
                            ->label('Скоро — показать без возможности оплаты')
                            ->default(false)->live()
                            ->afterStateUpdated(function ($state, $set): void {
                                if ($state) { $set('is_active', false); $set('is_enabled', false); }
                            })
                            ->helperText('На сайте появится пометка «Скоро». Выбрать этот способ и оформить через него заказ нельзя. Для запуска сначала выключите «Скоро», затем включите «Активен на сайте».'),
                        Toggle::make('is_active')
                            ->label('Активен на сайте')
                            ->default(true)
                            ->disabled(fn ($get): bool => (bool) $get('is_coming_soon'))->dehydrated()
                            ->helperText('Если выключено — способ скрыт и недоступен для новых заказов. Исключение: включённое состояние «Скоро» показывает недоступную заглушку.'),

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
