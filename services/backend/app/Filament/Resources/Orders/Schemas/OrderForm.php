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
        $recalculateCosts = function (callable $set, callable $get): void {
            $deliveryType = $get('delivery_type');
            $subtotal = (float) ($get('subtotal') ?? 0);

            $deliveryCost = 0.0;
            $assemblyCost = 0.0;

            $shippingLocationId = $get('shipping_location_id');
            $shippingMethodId = $get('shipping_method_id');
            $handlingTypeId = $get('delivery_handling_type_id');
            $floor = $get('delivery_floor') !== null ? (int) $get('delivery_floor') : null;
            $requiresAssembly = (bool) ($get('requires_assembly') ?? false);

            /** @var \App\Models\Shipping\ShippingLocation|null $location */
            $location = $shippingLocationId ? \App\Models\Shipping\ShippingLocation::find($shippingLocationId) : null;
            /** @var \App\Models\Shipping\DeliveryHandlingType|null $handlingType */
            $handlingType = $handlingTypeId ? \App\Models\Shipping\DeliveryHandlingType::find($handlingTypeId) : null;

            // Если выбран тип обработки, требующий этаж — без этажа стоимость не считаем (и не добавляем в цену)
            $canCalculateHandling = !$handlingType || !$handlingType->requires_floor || $floor !== null;

            if ($deliveryType === 'delivery') {
                if (!$location || $subtotal <= 0) {
                    $set('delivery_cost', 0);
                    $set('assembly_cost', 0);
                    return;
                }

                // Базовая доставка: либо по выбранному shipping_method (Vanilo),
                // либо по локации с учетом порога бесплатной доставки (через ShippingCalculationService)
                if ($shippingMethodId) {
                    $shippingMethod = \Vanilo\Shipment\Models\ShippingMethod::find($shippingMethodId);
                    if ($shippingMethod) {
                        $deliveryCost = app(\App\Services\Shipping\CarrierService::class)
                            ->calculateShippingMethodPrice($shippingMethod, $location, $subtotal);
                    } else {
                        $deliveryCost = (float) ($location->getEffectiveDeliveryPrice() ?? 0);
                    }
                } else {
                    // Берем только delivery_price (без обработки/сборки), чтобы не было двойного учета
                    $calculation = app(\App\Services\Shipping\ShippingCalculationService::class)
                        ->calculateShipping($location, $subtotal, null, null);
                    $deliveryCost = (float) ($calculation['delivery_price'] ?? 0);
                }

                // Обработка (подъем/разгрузка) добавляется к delivery_cost ровно один раз
                if ($handlingType && $location && $canCalculateHandling) {
                    $handlingPrice = $location->getDeliveryHandlingPrice($handlingType, $floor);
                    if ($handlingPrice !== null) {
                        $deliveryCost += (float) $handlingPrice;
                    }
                }

                // Сборка только если явно включена
                if ($requiresAssembly && $location) {
                    $assemblyPrice = $location->getEffectiveAssemblyPrice();
                    if ($assemblyPrice !== null) {
                        $assemblyCost = (float) $assemblyPrice;
                    }
                }
            } else { // pickup
                $deliveryCost = 0.0;

                if ($location) {
                    if ($requiresAssembly) {
                        $assemblyPrice = $location->getEffectiveAssemblyPrice();
                        if ($assemblyPrice !== null) {
                            $assemblyCost = (float) $assemblyPrice;
                        }
                    }

                    // Для pickup “обработка” добавляется в assembly_cost (как доп.услуга)
                    if ($handlingType && $canCalculateHandling) {
                        $handlingPrice = $location->getDeliveryHandlingPrice($handlingType, $floor);
                        if ($handlingPrice !== null) {
                            $assemblyCost += (float) $handlingPrice;
                        }
                    }
                }
            }

            $set('delivery_cost', max(0, round($deliveryCost, 2)));
            $set('assembly_cost', max(0, round($assemblyCost, 2)));
        };

        return $schema
            ->components([
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
                            ->options(['new' => __('filament/admin_sv/order_resource.status.new'), 'awaiting_payment' => __('filament/admin_sv/order_resource.status.awaiting_payment'), 'accepted' => __('filament/admin_sv/order_resource.status.accepted'), 'assembled' => __('filament/admin_sv/order_resource.status.assembled'), 'shipped' => __('filament/admin_sv/order_resource.status.shipped'), 'in_transit' => __('filament/admin_sv/order_resource.status.in_transit'), 'delivered' => __('filament/admin_sv/order_resource.status.delivered'), 'cancelled' => __('filament/admin_sv/order_resource.status.cancelled')])
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
                            ->options(['card' => __('filament/admin_sv/order_resource.payment_method.card'), 'cash' => __('filament/admin_sv/order_resource.payment_method.cash'), 'installment' => __('filament/admin_sv/order_resource.payment_method.installment')])
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

                Section::make('Информация о доставке')
                    ->schema([
                        Select::make('delivery_type')
                            ->label(__('filament/admin_sv/order_resource.delivery_type'))
                            ->options(['delivery' => __('filament/admin_sv/order_resource.delivery_type.delivery'), 'pickup' => __('filament/admin_sv/order_resource.delivery_type.pickup')])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) use ($recalculateCosts) {
                                if ($state !== 'delivery') {
                                    $set('shipping_location_id', null);
                                    $set('shipping_method_id', null);
                                    $set('delivery_handling_type_id', null);
                                    $set('delivery_floor', null);
                                    $set('requires_assembly', false);
                                }
                                $recalculateCosts($set, $get);
                            }),

                        Select::make('address_id')
                            ->label(__('filament/admin_sv/order_resource.address_id'))
                            ->relationship('address', 'city', function ($query) {
                                // Показываем все адреса, включая гостевые (без user_id)
                                return $query;
                            })
                            ->getOptionLabelUsing(function ($value) {
                                $address = \App\Models\Address\Address::with('user')->find($value);
                                if (!$address) {
                                    return null;
                                }
                                $label = $address->full_address;
                                if ($address->user_id && $address->user) {
                                    $label .= ' (Пользователь: ' . $address->user->name . ')';
                                } else {
                                    $label .= ' (Гостевой адрес)';
                                }
                                return $label;
                            })
                            ->searchable(['city', 'street', 'house'])
                            ->preload()
                            ->reactive(),
                        Placeholder::make('address_info')
                            ->label('Полный адрес')
                            ->content(function ($record, $get) {
                                $addressId = $get('address_id') ?? $record?->address_id;
                                if (!$addressId) {
                                    return 'Адрес не указан. Выберите адрес из списка выше.';
                                }
                                $address = \App\Models\Address\Address::with('user')->find($addressId);
                                if (!$address) {
                                    return 'Адрес не найден';
                                }
                                $info = $address->full_address;
                                if ($address->shipping_location_id) {
                                    $location = \App\Models\Shipping\ShippingLocation::find($address->shipping_location_id);
                                    if ($location) {
                                        $info .= "\nЛокация доставки: " . $location->name;
                                    }
                                }
                                return $info;
                            }),
                        Placeholder::make('delivery_type_info')
                            ->label('Тип доставки')
                            ->content(function ($record, $get) {
                                $deliveryType = $get('delivery_type') ?? $record?->delivery_type;
                                if (!$deliveryType) {
                                    return 'Не указан';
                                }
                                $typeLabel = match ($deliveryType) {
                                    'delivery' => 'Доставка курьером',
                                    'pickup' => 'Самовывоз',
                                    default => $deliveryType
                                };
                                return $typeLabel;
                            })
                            ->visible(fn($get, $record) => ($get('delivery_type') || $record?->delivery_type)),
                        Select::make('shipping_location_id')
                            ->label(__('filament/admin_sv/order_resource.shipping_location_id'))
                            ->relationship('shippingLocation', 'name', fn($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->getOptionLabelUsing(function ($value) {
                                $location = \App\Models\Shipping\ShippingLocation::find($value);
                                if (!$location) {
                                    return null;
                                }
                                // Показываем полный путь локации для лучшей читаемости
                                $path = [];
                                $current = $location;
                                while ($current) {
                                    array_unshift($path, $current->name);
                                    $current = $current->parent;
                                }
                                return implode(' > ', $path);
                            })
                            ->required(fn($get) => $get('delivery_type') === 'delivery')
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) use ($recalculateCosts) {
                                if (!$state && $get('delivery_type') === 'delivery') {
                                    $set('shipping_method_id', null);
                                }
                                $recalculateCosts($set, $get);
                            }),
                        Placeholder::make('shipping_location_info')
                            ->label('Информация о локации')
                            ->content(function ($record, $get) {
                                $locationId = $get('shipping_location_id') ?? $record?->shipping_location_id;
                                if (!$locationId) {
                                    return 'Не выбрана';
                                }
                                $location = \App\Models\Shipping\ShippingLocation::with('parent')->find($locationId);
                                if (!$location) {
                                    return 'Не найдена';
                                }
                                $typeLabel = match ($location->type) {
                                    'federal_district' => 'Федеральный округ',
                                    'region' => 'Регион',
                                    'locality' => 'Населенный пункт',
                                    default => $location->type
                                };
                                $info = "Тип: {$typeLabel}";
                                if ($location->parent) {
                                    $info .= "\nРодитель: " . $location->parent->name;
                                }
                                $deliveryPrice = $location->getEffectiveDeliveryPrice();
                                if ($deliveryPrice !== null) {
                                    $info .= "\nБазовая стоимость доставки: " . number_format($deliveryPrice, 2, '.', ' ') . " ₽";
                                }
                                return $info;
                            })
                            ->visible(fn($get, $record) => ($get('shipping_location_id') || $record?->shipping_location_id)),
                        Select::make('shipping_method_id')
                            ->label(__('filament/admin_sv/order_resource.shipping_method_id'))
                            ->options(function ($get) {
                                $locationId = $get('shipping_location_id');
                                $selectedId = $get('shipping_method_id');

                                $options = [];

                                // 1) Если локация выбрана — показываем методы для этой локации (через CarrierService)
                                if ($locationId) {
                                    $location = \App\Models\Shipping\ShippingLocation::find($locationId);
                                    if ($location) {
                                        $methods = app(\App\Services\Shipping\CarrierService::class)->getAvailableShippingMethods($location);
                                        foreach ($methods as $m) {
                                            $id = $m['id'];
                                            $carrierName = $m['carrier']['name'] ?? null;
                                            $name = $m['name'] ?? ('Метод #' . $id);
                                            $options[$id] = ($carrierName ? ($carrierName . ' — ') : '') . $name;
                                        }
                                    }
                                }

                                // 2) Если в заказе/форме уже стоит shipping_method_id, но его нет в options —
                                // добавим его отдельно, чтобы в редактировании он отображался (даже если "не соответствует локации")
                                if ($selectedId && !isset($options[$selectedId])) {
                                    $method = \Vanilo\Shipment\Models\ShippingMethod::with('carrier')->find($selectedId);
                                    if ($method) {
                                        $carrierName = $method->carrier?->name ?? null;
                                        $label = ($carrierName ? ($carrierName . ' — ') : '') . $method->name . ' (не соответствует выбранной локации)';
                                        $options = [$method->id => $label] + $options;
                                    }
                                }

                                return $options;
                            })
                            ->reactive()
                            ->searchable()
                            ->disabled(fn($get) => $get('delivery_type') !== 'delivery' || !$get('shipping_location_id'))
                            ->helperText('Выберите службу доставки (список зависит от локации)')
                            ->afterStateUpdated(function ($state, $set, $get) use ($recalculateCosts) {
                                $recalculateCosts($set, $get);
                            }),
                        Placeholder::make('carrier_info')
                            ->label('Информация о перевозчике')
                            ->content(function ($record, $get) {
                                $methodId = $get('shipping_method_id') ?? $record?->shipping_method_id;
                                if (!$methodId) {
                                    return 'Не выбран. Если не выбран, будет использована базовая стоимость доставки локации.';
                                }
                                $method = \Vanilo\Shipment\Models\ShippingMethod::with('carrier')->find($methodId);
                                if (!$method) {
                                    return 'Метод доставки не найден';
                                }
                                if (!$method->carrier) {
                                    return 'Перевозчик не указан';
                                }
                                $carrier = $method->carrier;
                                $carrierName = isset($carrier->name) ? $carrier->name : 'Не указано';
                                $info = "Перевозчик: {$carrierName}";
                                // Проверяем описание в configuration, если оно есть
                                $config = $carrier->configuration ?? [];
                                if (is_array($config) && isset($config['description'])) {
                                    $info .= "\nОписание: " . $config['description'];
                                }
                                return $info;
                            })
                            ->visible(fn($get, $record) => ($get('shipping_method_id') || $record?->shipping_method_id)),
                        Select::make('delivery_handling_type_id')
                            ->label(__('filament/admin_sv/order_resource.delivery_handling_type_id'))
                            ->relationship('deliveryHandlingType', 'name', fn($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->helperText('Тип обработки: разгрузка, подъем на этаж и т.д.')
                            ->afterStateUpdated(function ($state, $set, $get) use ($recalculateCosts) {
                                if (!$state) {
                                    $set('delivery_floor', null);
                                }
                                $recalculateCosts($set, $get);
                            }),
                        Placeholder::make('handling_type_info')
                            ->label('Информация о типе обработки')
                            ->content(function ($record, $get) {
                                $handlingTypeId = $get('delivery_handling_type_id') ?? $record?->delivery_handling_type_id;
                                if (!$handlingTypeId) {
                                    return 'Не выбран';
                                }
                                $handlingType = \App\Models\Shipping\DeliveryHandlingType::find($handlingTypeId);
                                if (!$handlingType) {
                                    return 'Не найден';
                                }
                                $info = $handlingType->name;
                                if ($handlingType->description) {
                                    $info .= "\n" . $handlingType->description;
                                }
                                if ($handlingType->requires_floor) {
                                    $info .= "\nВнимание: требуется указать этаж";
                                }
                                return $info;
                            })
                            ->visible(fn($get, $record) => ($get('delivery_handling_type_id') || $record?->delivery_handling_type_id)),
                        TextInput::make('delivery_floor')
                            ->label(__('filament/admin_sv/order_resource.delivery_floor'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->helperText('Обязательно для типов обработки, требующих указание этажа')
                            ->required(function ($get) {
                                $handlingTypeId = $get('delivery_handling_type_id');
                                if (!$handlingTypeId) {
                                    return false;
                                }
                                $handlingType = \App\Models\Shipping\DeliveryHandlingType::find($handlingTypeId);
                                return (bool) ($handlingType?->requires_floor);
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) use ($recalculateCosts) {
                                $recalculateCosts($set, $get);
                            })
                            ->visible(function ($get, $record) {
                                $handlingTypeId = $get('delivery_handling_type_id') ?? $record?->delivery_handling_type_id;
                                if (!$handlingTypeId) {
                                    return false;
                                }
                                $handlingType = \App\Models\Shipping\DeliveryHandlingType::find($handlingTypeId);
                                return $handlingType && ($handlingType->requires_floor || $get('delivery_floor') || $record?->delivery_floor);
                            }),
                        \Filament\Forms\Components\Toggle::make('requires_assembly')
                            ->label(__('filament/admin_sv/order_resource.requires_assembly'))
                            ->default(false)
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) use ($recalculateCosts) {
                                $recalculateCosts($set, $get);
                            }),
                        DatePicker::make('delivery_date')
                            ->label(__('filament/admin_sv/order_resource.delivery_date')),
                        TextInput::make('delivery_time')
                            ->label(__('filament/admin_sv/order_resource.delivery_time'))
                            ->maxLength(255),
                    ])->columns(2)
                    ->collapsible(),

                Section::make('Стоимость')
                    ->schema([
                        TextInput::make('subtotal')
                            ->label(__('filament/admin_sv/order_resource.subtotal'))
                            ->numeric()
                            ->prefix('₽')
                            ->required()
                            ->disabled(fn($record) => $record !== null)
                            ->dehydrated()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) use ($recalculateCosts) {
                                $recalculateCosts($set, $get);
                            }),
                        TextInput::make('delivery_cost')
                            ->label(__('filament/admin_sv/order_resource.delivery_cost'))
                            ->numeric()
                            ->prefix('₽')
                            ->default(0)
                            ->disabled(fn($record) => $record !== null)
                            ->dehydrated()
                            ->helperText(function ($record, $get) {
                                if ($record) {
                                    $info = [];
                                    if ($record->shippingLocation) {
                                        $info[] = 'Локация: ' . $record->shippingLocation->name;
                                    }
                                    if ($record->shippingMethod) {
                                        $info[] = 'Служба: ' . $record->shippingMethod->name;
                                    }
                                    if ($record->deliveryHandlingType) {
                                        $info[] = 'Обработка: ' . $record->deliveryHandlingType->name;
                                        if ($record->delivery_floor) {
                                            $info[] = 'Этаж: ' . $record->delivery_floor;
                                        }
                                    }
                                    return $info ? implode(' | ', $info) : 'Рассчитана автоматически';
                                }
                                return 'Будет рассчитана автоматически при создании заказа';
                            }),
                        TextInput::make('assembly_cost')
                            ->label(__('filament/admin_sv/order_resource.assembly_cost'))
                            ->numeric()
                            ->prefix('₽')
                            ->default(0)
                            ->disabled(fn($record) => $record !== null)
                            ->dehydrated()
                            ->helperText(function ($record) {
                                if (!$record) {
                                    return 'Включает стоимость сборки (если требуется) и обработки доставки (подъем на этаж и т.д.)';
                                }
                                $info = [];
                                if ($record->requires_assembly) {
                                    $info[] = 'Сборка: включена';
                                }
                                if ($record->deliveryHandlingType) {
                                    $info[] = 'Обработка: ' . $record->deliveryHandlingType->name;
                                    if ($record->delivery_floor) {
                                        $info[] = 'Этаж: ' . $record->delivery_floor;
                                    }
                                }
                                return $info ? implode(' | ', $info) : 'Не включена';
                            }),
                        TextInput::make('total')
                            ->label(__('filament/admin_sv/order_resource.total'))
                            ->numeric()
                            ->prefix('₽')
                            ->required()
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn($get, $record) =>
                                ($record ? (float) $record->total : 0) ?:
                                (($get('subtotal') ?? 0) + ($get('delivery_cost') ?? 0) + ($get('assembly_cost') ?? 0))),
                    ])->columns(4),

                Textarea::make('comment')
                    ->label(__('filament/admin_sv/order_resource.comment'))
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }
}
