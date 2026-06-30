<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Products\ProductResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextEntry::make('number')
                            ->label('Номер заказа')
                            ->size('lg')
                            ->weight('bold')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'new' => 'Новый',
                                'awaiting_payment' => 'Ожидание оплаты',
                                'accepted' => 'Принят',
                                'assembled' => 'Собран',
                                'shipped' => 'Отправлен',
                                'in_transit' => 'В пути',
                                'delivered' => 'Доставлен',
                                'cancelled' => 'Отменен',
                                default => $state,
                            })
                            ->color(fn ($state) => match ($state) {
                                'new' => 'info',
                                'awaiting_payment' => 'warning',
                                'accepted' => 'primary',
                                'assembled' => 'warning',
                                'shipped' => 'success',
                                'in_transit' => 'success',
                                'delivered' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                    ])
                    ->columns(2),

                Section::make('Адрес доставки')
                    ->schema([
                        TextEntry::make('address_snapshot')
                            ->label('Адрес (сохранен в заказе)')
                            ->state(fn ($record) => $record->address_snapshot ?: $record->address?->full_address)
                            ->placeholder('Адрес не указан')
                            ->icon('heroicon-o-map-pin')
                            ->columnSpanFull(),
                        TextEntry::make('delivery_type')
                            ->label('Тип доставки')
                            ->badge()
                            ->formatStateUsing(fn($state) => match ($state) {
                                'delivery' => 'Доставка курьером',
                                'pickup' => 'Самовывоз',
                                default => $state,
                            })
                            ->color(fn($state) => match ($state) {
                                'delivery' => 'primary',
                                'pickup' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('shippingLocation.name')
                            ->label('Локация доставки')
                            ->placeholder('—')
                            ->icon('heroicon-o-map-pin'),
                        TextEntry::make('shippingMethod.name')
                            ->label('Служба доставки')
                            ->placeholder('—')
                            ->formatStateUsing(function ($state, $record) {
                                if (!$state && $record->shippingMethod) {
                                    $carrierName = $record->shippingMethod?->carrier?->name;
                                    return $carrierName ? ($carrierName . ' — ' . $record->shippingMethod->name) : $record->shippingMethod->name;
                                }
                                return $state ?: '—';
                            })
                            ->icon('heroicon-o-truck'),
                        TextEntry::make('deliveryHandlingType.name')
                            ->label('Тип обработки')
                            ->placeholder('—')
                            ->icon('heroicon-o-cog'),
                        TextEntry::make('delivery_floor')
                            ->label('Этаж')
                            ->placeholder('—')
                            ->visible(fn($record) => $record->delivery_floor !== null),
                        TextEntry::make('requires_assembly')
                            ->label('Требуется сборка')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Да' : 'Нет')
                            ->color(fn($state) => $state ? 'warning' : 'gray'),
                        TextEntry::make('delivery_date')
                            ->label('Дата доставки')
                            ->date('d.m.Y')
                            ->placeholder('—')
                            ->icon('heroicon-o-calendar')
                            ->visible(fn($record) => $record->delivery_date !== null),
                        TextEntry::make('delivery_time')
                            ->label('Время доставки')
                            ->placeholder('—')
                            ->icon('heroicon-o-clock')
                            ->visible(fn($record) => $record->delivery_time !== null),
                    ])
                    ->columns(3),

                Section::make('Оплата')
                    ->description('Статус и сумма платежа по заказу. Управление — кнопка «Оплата» в шапке или вкладка «Платежи».')
                    ->schema([
                        TextEntry::make('payment_method')
                            ->label('Способ оплаты')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'card' => 'Карта',
                                'card_in_store' => 'Карта (онлайн)',
                                'card_ecom' => 'Карта (онлайн e-commerce)',
                                'cash' => 'Наличные',
                                'installment' => 'Рассрочка',
                                default => $state ?? '—',
                            })
                            ->color(fn ($state) => match ($state) {
                                'card' => 'success',
                                'card_in_store' => 'success',
                                'card_ecom' => 'success',
                                'cash' => 'warning',
                                'installment' => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('payment_status_summary')
                            ->label('Статус платежа')
                            ->badge()
                            ->state(function ($record) {
                                $payment = $record->payments()->orderByDesc('id')->first();
                                if (!$payment) {
                                    return 'Нет платежа';
                                }
                                $status = $payment->getStatus();
                                $labels = [
                                    'pending' => 'Ожидает',
                                    'authorized' => 'Авторизован',
                                    'on_hold' => 'На удержании',
                                    'paid' => 'Оплачен',
                                    'partially_paid' => 'Частично оплачен',
                                    'declined' => 'Отклонён',
                                    'timeout' => 'Истёк',
                                    'cancelled' => 'Отменён',
                                    'refunded' => 'Возвращён',
                                    'partially_refunded' => 'Частично возвращён',
                                ];
                                return $labels[strtolower((string) $status->value())] ?? (string) $status->value();
                            })
                            ->color(function ($record) {
                                $payment = $record->payments()->orderByDesc('id')->first();
                                if (!$payment) {
                                    return 'gray';
                                }
                                $s = strtolower((string) $payment->getStatus()->value());
                                return match ($s) {
                                    'paid' => 'success',
                                    'cancelled', 'declined', 'timeout' => 'danger',
                                    'partially_paid', 'authorized', 'on_hold' => 'warning',
                                    default => 'gray',
                                };
                            }),
                        TextEntry::make('payment_amount_summary')
                            ->label('Оплачено')
                            ->state(function ($record) {
                                $payment = $record->payments()->orderByDesc('id')->first();
                                $total = (float) $record->total;
                                if (!$payment) {
                                    return '0 ₽ из ' . number_format($total, 2, '.', ' ') . ' ₽';
                                }
                                $paid = (float) $payment->getAmountPaid();
                                if ($paid >= $total && $total > 0) {
                                    return 'Оплачено полностью';
                                }
                                return number_format($paid, 2, '.', ' ') . ' ₽ из ' . number_format($total, 2, '.', ' ') . ' ₽';
                            })
                            ->icon('heroicon-o-banknotes'),
                        TextEntry::make('payment_remote_id')
                            ->label('ID транзакции')
                            ->state(fn ($record) => $record->payments()->orderByDesc('id')->first()?->remote_id ?? '—')
                            ->placeholder('—')
                            ->copyable()
                            ->visible(fn ($record) => (string) ($record->payments()->orderByDesc('id')->first()?->remote_id ?? '') !== ''),
                    ])
                    ->columns(2),

                Section::make('Состав заказа')
                    ->schema([
                        TextEntry::make('items_summary')
                            ->label('Общая информация')
                            ->state(function ($record) {
                                if (!$record->relationLoaded('items') || !$record->items) {
                                    return 'Товары не загружены';
                                }
                                $itemsCount = $record->items->count();
                                $totalQuantity = $record->items->sum('quantity');
                                return "Позиций: {$itemsCount} | Общее количество товаров: {$totalQuantity} шт.";
                            })
                            ->columnSpanFull(),
                        RepeatableEntry::make('items')
                            ->label('Товары')
                            ->schema([
                                TextEntry::make('product.name')
                                    ->label('Товар')
                                    ->placeholder('Товар удален')
                                    ->url(function ($record): ?string {
                                        $product = $record->product;

                                        return $product
                                            ? ProductResource::getUrl('edit', ['record' => $product])
                                            : null;
                                    })
                                    ->openUrlInNewTab(),
                                TextEntry::make('quantity')
                                    ->label('Количество')
                                    ->numeric(),
                                TextEntry::make('price')
                                    ->label('Цена за единицу')
                                    ->money('RUB')
                                    ->numeric(),
                                TextEntry::make('total')
                                    ->label('Итого')
                                    ->money('RUB')
                                    ->numeric()
                                    ->weight('bold'),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                        TextEntry::make('subtotal')
                            ->label('Общая стоимость товаров')
                            ->money('RUB')
                            ->numeric()
                            ->size('lg')
                            ->weight('bold')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

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
                                    return $record->shippingLocation ? 'Локация: ' . $record->shippingLocation->name : '';
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

                Section::make('Покупатель')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Пользователь')
                            ->placeholder('Гостевой заказ')
                            ->icon('heroicon-o-user'),
                        TextEntry::make('user.email')
                            ->label('Email пользователя')
                            ->placeholder('—')
                            ->icon('heroicon-o-envelope')
                            ->copyable(),
                        TextEntry::make('contact_name')
                            ->label('Контактное лицо')
                            ->icon('heroicon-o-user'),
                        TextEntry::make('contact_phone')
                            ->label('Телефон')
                            ->icon('heroicon-o-phone')
                            ->copyable(),
                        TextEntry::make('contact_email')
                            ->label('Email')
                            ->icon('heroicon-o-envelope')
                            ->copyable(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),

                Section::make('Техническая информация')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Дата создания')
                            ->dateTime('d.m.Y H:i')
                            ->icon('heroicon-o-calendar'),
                        TextEntry::make('updated_at')
                            ->label('Последнее обновление')
                            ->dateTime('d.m.Y H:i')
                            ->icon('heroicon-o-clock'),
                        TextEntry::make('number')
                            ->label('Номер заказа')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'new' => 'Новый',
                                'awaiting_payment' => 'Ожидание оплаты',
                                'accepted' => 'Принят',
                                'assembled' => 'Собран',
                                'shipped' => 'Отправлен',
                                'in_transit' => 'В пути',
                                'delivered' => 'Доставлен',
                                'cancelled' => 'Отменен',
                                default => $state,
                            }),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Section::make('Дополнительная информация')
                    ->schema([
                        TextEntry::make('comment')
                            ->label('Комментарий к заказу')
                            ->placeholder('Комментарий отсутствует')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),

                Section::make('История статусов')
                    ->schema([
                        RepeatableEntry::make('statusHistory')
                            ->label('История')
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Статус')
                                    ->badge()
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        'new' => 'Новый',
                                        'accepted' => 'Принят',
                                        'assembled' => 'Собран',
                                        'shipped' => 'Отправлен',
                                        'in_transit' => 'В пути',
                                        'delivered' => 'Доставлен',
                                        'cancelled' => 'Отменен',
                                        default => $state,
                                    }),
                                TextEntry::make('created_at')
                                    ->label('Дата и время')
                                    ->dateTime('d.m.Y H:i:s'),
                                TextEntry::make('user.name')
                                    ->label('Изменил')
                                    ->placeholder('Система'),
                                TextEntry::make('comment')
                                    ->label('Комментарий')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),
                Section::make('Дополнительные услуги')
                    ->schema([
                        RepeatableEntry::make('additionalServices')
                            ->label('Услуги')
                            ->schema([
                                TextEntry::make('pivot.service_name')
                                    ->label('Название услуги')
                                    ->formatStateUsing(fn($state, $record) => $state ?: $record->name),
                                TextEntry::make('pivot.price')
                                    ->label('Стоимость')
                                    ->money('RUB')
                                    ->formatStateUsing(function ($state, $record) {
                                        $priceType = $record->pivot->price_type ?? $record->price_type ?? 'fixed';
                                        if ($priceType === 'custom') {
                                            return 'По договоренности';
                                        }
                                        if ($priceType === 'from') {
                                            return 'от ' . number_format((float) $state, 2, '.', ' ') . ' ₽';
                                        }
                                        return number_format((float) $state, 2, '.', ' ') . ' ₽';
                                    }),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn($record) => $record->additionalServices && $record->additionalServices->isNotEmpty()),
                        TextEntry::make('empty_services')
                            ->label('')
                            ->state('Дополнительные услуги не добавлены')
                            ->placeholder('—')
                            ->visible(fn($record) => !$record->additionalServices || $record->additionalServices->isEmpty()),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
