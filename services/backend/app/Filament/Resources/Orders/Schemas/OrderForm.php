<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Охлаждение и передача')->collapsed()->schema([
                    Placeholder::make('cooling_state')->label('Состояние')->content(fn ($record) => $record
                        ? $record->getStatusLabel().' · до '.($record->cooling_until?->format('d.m.Y H:i:s') ?? '—')
                            .' · отправка: '.($record->integration_state ?? '—') : 'После оформления'),
                    Placeholder::make('refund_review')->label('Возврат при отмене')->content(fn ($record) => $record
                        ? (\Illuminate\Support\Facades\DB::table('order_refund_tasks')->where('order_id', $record->id)->value('status') ?? 'Не требуется') : '—'),
                ])->columnSpanFull(),
                Section::make('Детализация заказа')
                    ->schema([
                        Placeholder::make('buyer_summary')
                            ->label('Покупатель')
                            ->content(function ($record, $get) {
                                if (!$record) {
                                    return 'Будет доступно после сохранения заказа.';
                                }

                                $lines = [];
                                $lines[] = $record->user
                                    ? ('Пользователь: ' . $record->user->name . ' (ID: ' . $record->user->id . ')')
                                    : 'Гостевой заказ (без регистрации)';

                                $lines[] = 'Контакт: ' . ($record->contact_name ?: '—');
                                $lines[] = 'Телефон: ' . ($record->contact_phone ?: '—');
                                $lines[] = 'Email: ' . ($record->contact_email ?: '—');

                                if ($record->address_snapshot) {
                                    $lines[] = 'Адрес: ' . $record->address_snapshot;
                                } elseif ($record->address) {
                                    $lines[] = 'Адрес: ' . $record->address->full_address;
                                } else {
                                    $lines[] = 'Адрес: —';
                                }

                                return implode("\n", $lines);
                            }),

                        Placeholder::make('items_summary')
                            ->label('Состав заказа (что купили)')
                            ->content(function ($record) {
                                if (!$record) {
                                    return 'Будет доступно после сохранения заказа.';
                                }

                                $items = $record->items ?? collect();
                                if ($items->isEmpty()) {
                                    return 'Товары не найдены (возможно, заказ создан без позиций).';
                                }

                                $lines = [];
                                $lines[] = 'Позиций: ' . $items->count();

                                foreach ($items as $item) {
                                    $name = $item->product?->name ?? ('Товар #' . $item->product_id);
                                    $qty = (int) $item->quantity;
                                    $price = (float) $item->price;
                                    $total = (float) $item->total;
                                    $lines[] = "- {$name}: {$qty} × " . number_format($price, 2, '.', ' ') . ' ₽ = ' . number_format($total, 2, '.', ' ') . ' ₽';
                                }

                                $lines[] = 'Сумма товаров (subtotal): ' . number_format((float) $record->subtotal, 2, '.', ' ') . ' ₽';
                                return implode("\n", $lines);
                            }),

                        Placeholder::make('services_summary')
                            ->label('Услуги (доставка/подъем/сборка)')
                            ->content(function ($record) {
                                if (!$record) {
                                    return 'Будет доступно после сохранения заказа.';
                                }

                                $lines = [];
                                $deliveryTypeLabel = match ($record->delivery_type) {
                                    'delivery' => 'Доставка',
                                    'pickup' => 'Самовывоз',
                                    default => $record->delivery_type ?: '—',
                                };
                                $lines[] = 'Тип: ' . $deliveryTypeLabel;

                                if ($record->shippingLocation) {
                                    $lines[] = 'Локация: ' . $record->shippingLocation->name;
                                } else {
                                    $lines[] = 'Локация: —';
                                }

                                if ($record->shippingMethod) {
                                    $carrierName = $record->shippingMethod?->carrier?->name;
                                    $lines[] = 'Служба доставки: ' . ($carrierName ? ($carrierName . ' — ') : '') . $record->shippingMethod->name;

                                    // Добавляем информацию о доставке из сохраненных данных
                                    if ($record->delivery_days_min || $record->delivery_days_max) {
                                        $daysInfo = '';
                                        if ($record->delivery_days_min && $record->delivery_days_max) {
                                            $daysInfo = $record->delivery_days_min . '-' . $record->delivery_days_max . ' дн.';
                                        } elseif ($record->delivery_days_min) {
                                            $daysInfo = 'от ' . $record->delivery_days_min . ' дн.';
                                        } elseif ($record->delivery_days_max) {
                                            $daysInfo = 'до ' . $record->delivery_days_max . ' дн.';
                                        }
                                        if ($daysInfo) {
                                            $lines[] = 'Срок доставки: ' . $daysInfo;
                                        }
                                    }

                                    if ($record->delivery_base_price !== null) {
                                        $lines[] = 'Базовая цена доставки: ' . number_format((float) $record->delivery_base_price, 2, '.', ' ') . ' ₽';
                                    }

                                    if ($record->delivery_free_threshold !== null) {
                                        $lines[] = 'Порог бесплатной доставки: ' . number_format((float) $record->delivery_free_threshold, 2, '.', ' ') . ' ₽';
                                    }
                                } else {
                                    $lines[] = 'Служба доставки: —';
                                }

                                if ($record->deliveryHandlingType) {
                                    $lines[] = 'Обработка: ' . $record->deliveryHandlingType->name;
                                    $lines[] = 'Этаж: ' . ($record->delivery_floor ?: '—');
                                } else {
                                    $lines[] = 'Обработка: —';
                                }

                                $lines[] = 'Сборка: ' . ($record->requires_assembly ? 'да' : 'нет');

                                $lines[] = 'Стоимость доставки: ' . number_format((float) $record->delivery_cost, 2, '.', ' ') . ' ₽';
                                $lines[] = 'Стоимость сборки/услуг: ' . number_format((float) $record->assembly_cost, 2, '.', ' ') . ' ₽';

                                // Дополнительные услуги
                                if ($record->relationLoaded('additionalServices') && $record->additionalServices->isNotEmpty()) {
                                    $lines[] = '';
                                    $lines[] = 'Дополнительные услуги:';
                                    foreach ($record->additionalServices as $service) {
                                        $serviceName = $service->pivot->service_name ?? $service->name;
                                        $servicePrice = (float) ($service->pivot->price ?? 0);
                                        $priceType = $service->pivot->price_type ?? $service->price_type;

                                        $priceDisplay = match ($priceType) {
                                            'from' => 'от ' . number_format($servicePrice, 2, '.', ' ') . ' ₽',
                                            'custom' => 'По договоренности',
                                            default => number_format($servicePrice, 2, '.', ' ') . ' ₽',
                                        };

                                        $lines[] = "  • {$serviceName}: {$priceDisplay}";
                                    }
                                }

                                // Доп. пояснение, почему могли получиться такие цифры
                                if ($record->delivery_type === 'delivery' && !$record->shippingMethod) {
                                    $lines[] = '';
                                    $lines[] = 'Примечание: служба доставки не выбрана — стоимость могла быть рассчитана по базовым настройкам локации.';
                                }

                                return implode("\n", $lines);
                            }),

                        Placeholder::make('status_history_summary')
                            ->label('История статусов')
                            ->content(function ($record) {
                                if (!$record) {
                                    return 'Будет доступно после сохранения заказа.';
                                }

                                $history = $record->statusHistory ?? collect();
                                if ($history->isEmpty()) {
                                    return 'История статусов пуста.';
                                }

                                $lines = [];
                                foreach ($history as $h) {
                                    $when = $h->created_at?->format('Y-m-d H:i');
                                    $by = $h->user?->name ? (' (' . $h->user->name . ')') : '';
                                    $comment = $h->comment ? (' — ' . $h->comment) : '';
                                    $lines[] = ($when ? "[{$when}] " : '') . $h->status . $by . $comment;
                                }

                                return implode("\n", $lines);
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record === null),

                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('number')
                            ->label(__('filament/admin_sv/order_resource.number'))
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('status')
                            ->label(__('filament/admin_sv/order_resource.status'))
                            ->options(fn ($record) => $record?->cooling_until
                                ? array_diff_key(\App\Models\Order\OrderStatus::options(), array_flip(['new', 'awaiting_payment', 'accepted']))
                                : \App\Models\Order\OrderStatus::options())
                            ->required(),
                        Select::make('user_id')
                            ->label(__('filament/admin_sv/order_resource.user_id'))
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Гостевой заказ')
                            ->helperText(fn($record) => $record && !$record->user_id ?
                                'Заказ создан без регистрации. Контакт: ' . $record->contact_name . ' (' . $record->contact_email . ')' : null),
                    ])->columns(3),

                Section::make('Контактная информация')
                    ->schema([
                        TextInput::make('contact_name')
                            ->label(__('filament/admin_sv/order_resource.contact_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label(__('filament/admin_sv/order_resource.contact_phone'))
                            ->required()
                            ->maxLength(20),
                        TextInput::make('contact_email')
                            ->label(__('filament/admin_sv/order_resource.contact_email'))
                            ->email()
                            ->required()
                            ->maxLength(255),
                    ])->columns(3),

                Section::make('Оплата')
                    ->schema([
                        Select::make('payment_method')
                            ->label(__('filament/admin_sv/order_resource.payment_method'))
                            ->disabled(fn ($record) => $record?->checkout_snapshot !== null)
                            ->options(fn () => \App\Models\Payment\PaymentMethod::query()->pluck('name', 'code')->all() + ['card' => __('filament/admin_sv/order_resource.payment_method.card'), 'cash' => __('filament/admin_sv/order_resource.payment_method.cash'), 'installment' => __('filament/admin_sv/order_resource.payment_method.installment')])
                            ->required(),
                    ])->columns(1),


                Section::make('Детализация стоимости')
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Товары (общая стоимость)')
                            ->money('RUB')
                            ->numeric()
                            ->size('lg')
                            ->weight('bold')
                            ->columnSpanFull(),
                        TextEntry::make('items_count')
                            ->label('Количество позиций')
                            ->state(fn($record) => $record->items ? $record->items->count() : 0)
                            ->suffix(' шт.')
                            ->numeric()
                            ->columnSpanFull(),
                        TextEntry::make('items_total_quantity')
                            ->label('Общее количество товаров')
                            ->state(fn($record) => $record->items ? $record->items->sum('quantity') : 0)
                            ->suffix(' шт.')
                            ->numeric()
                            ->columnSpanFull(),
                        TextEntry::make('delivery_cost')
                            ->label('Доставка')
                            ->money('RUB')
                            ->numeric()
                            ->placeholder('0,00 ₽')
                            ->helperText(function ($record) {
                                if ($record->delivery_type === 'delivery') {
                                    return isset($record->fulfillment_snapshot['warehouse_external_id']) ? 'Склад: '.$record->fulfillment_snapshot['warehouse_external_id'] : '';
                                }
                                return 'Самовывоз';
                            }),
                        TextEntry::make('assembly_cost')
                            ->label('Сборка')
                            ->money('RUB')
                            ->numeric()
                            ->placeholder('0,00 ₽')
                            ->helperText(function ($record) {
                                return $record->requires_assembly ? 'Требуется сборка' : 'Сборка не требуется';
                            }),
                        TextEntry::make('additional_services_total')
                            ->label('Дополнительные услуги')
                            ->money('RUB')
                            ->state(function ($record) {
                                if (array_key_exists('additional_services_cost', $record->fulfillment_snapshot ?? [])) {
                                    return (float) $record->fulfillment_snapshot['additional_services_cost'];
                                }
                                if (!$record->relationLoaded('additionalServices') || !$record->additionalServices) {
                                    return 0;
                                }
                                return (float) $record->additionalServices->sum(function ($service) {
                                    $priceType = $service->pivot->price_type ?? $service->price_type ?? 'fixed';
                                    if ($priceType === 'custom') {
                                        return 0;
                                    }
                                    return (float) ($service->pivot->price ?? 0);
                                });
                            })
                            ->numeric()
                            ->placeholder('0,00 ₽')
                            ->helperText(function ($record) {
                                if (isset($record->fulfillment_snapshot['additional_services'])) {
                                    return 'Добавлено услуг: '.count($record->fulfillment_snapshot['additional_services']);
                                }
                                if (!$record->relationLoaded('additionalServices') || !$record->additionalServices) {
                                    return 'Дополнительные услуги не добавлены';
                                }
                                $count = $record->additionalServices->count();
                                return $count > 0 ? "Добавлено услуг: {$count}" : 'Дополнительные услуги не добавлены';
                            }),
                        TextEntry::make('total')
                            ->label('ИТОГО К ОПЛАТЕ')
                            ->money('RUB')
                            ->numeric()
                            ->size('lg')
                            ->weight('bold')
                            ->color('success')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Оплата')
                    ->schema([
                        TextEntry::make('payment_method')
                            ->label('Способ оплаты')
                            ->badge()
                            ->formatStateUsing(fn($state) => match ($state) {
                                'card' => 'Карта',
                                'cash' => 'Наличные',
                                'installment' => 'Рассрочка',
                                default => $state,
                            })
                            ->color(fn($state) => match ($state) {
                                'card' => 'success',
                                'cash' => 'warning',
                                'installment' => 'info',
                                default => 'gray',
                            }),
                    ])
                    ->columns(1),

                Section::make('Сохранённые условия исполнения')
                    ->description('Адрес, склад, услуги и цены фиксируются расчётом складского API при оформлении. Для изменения условий требуется новый расчёт и переоформление заказа.')
                    ->schema([
                        Placeholder::make('fulfillment_source')
                            ->label('Склад / точка выдачи')
                            ->content(fn ($record): string => $record?->fulfillment_snapshot['warehouse_name'] ?? $record?->fulfillment_snapshot['name'] ?? 'Исторический заказ — условия сохранены без пересчёта'),
                        Placeholder::make('fulfillment_address')
                            ->label('Адрес')
                            ->content(fn ($record): string => $record?->address_snapshot ?? '—'),
                    ])->columns(2),

                Textarea::make('comment')
                    ->label(__('filament/admin_sv/order_resource.comment'))
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }
}
