<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ {{ $orderData->number }}</title>
    <style>
        @media print {
            body {
                margin: 0;
                padding: 20px;
            }
            .no-print {
                display: none !important;
            }
            @page {
                margin: 1cm;
            }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            padding: 20px;
            background: #fff;
        }
        
        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
        }
        
        .header {
            border-bottom: 3px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .header-info {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: bold;
            font-size: 11px;
            color: #666;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        
        .info-value {
            font-size: 13px;
            color: #000;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table th,
        table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        table th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
        }
        
        table td {
            font-size: 12px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .total-section {
            margin-top: 20px;
            border-top: 2px solid #333;
            padding-top: 15px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
        }
        
        .total-row.final {
            font-size: 18px;
            font-weight: bold;
            border-top: 2px solid #333;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-new { background: #e3f2fd; color: #1976d2; }
        .badge-accepted { background: #e8f5e9; color: #388e3c; }
        .badge-assembled { background: #fff3e0; color: #f57c00; }
        .badge-shipped { background: #e8f5e9; color: #388e3c; }
        .badge-in_transit { background: #e8f5e9; color: #388e3c; }
        .badge-delivered { background: #e8f5e9; color: #388e3c; }
        .badge-cancelled { background: #ffebee; color: #d32f2f; }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #333;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #555;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
        
        .status-history {
            margin-top: 15px;
        }
        
        .status-item {
            padding: 8px;
            border-left: 3px solid #ddd;
            margin-bottom: 10px;
            padding-left: 15px;
        }
        
        .status-date {
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Печать</button>
    
    <div class="container">
        <div class="header">
            <h1>ЗАКАЗ № {{ $orderData->number }}</h1>
            <div class="header-info">
                <div>
                    <div class="info-label">Дата создания</div>
                    <div class="info-value">{{ \Carbon\Carbon::parse($orderData->created_at)->format('d.m.Y H:i') }}</div>
                </div>
                <div>
                    <div class="info-label">Статус</div>
                    <div class="info-value">
                        @php
                            // Используем статус, переданный из контроллера
                            $statusValue = (string) ($orderStatus ?? '');
                            $statusLabel = match ($statusValue) {
                                'new' => 'Новый',
                                'accepted' => 'Принят',
                                'assembled' => 'Собран',
                                'shipped' => 'Отправлен',
                                'in_transit' => 'В пути',
                                'delivered' => 'Доставлен',
                                'cancelled' => 'Отменен',
                                default => $statusValue,
                            };
                        @endphp
                        <span class="badge badge-{{ $statusValue }}">
                            {{ $statusLabel }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Покупатель</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Пользователь</div>
                    <div class="info-value">{{ $user ? $user->name . ' (ID: ' . $user->id . ')' : 'Гостевой заказ' }}</div>
                </div>
                @if($user)
                <div class="info-item">
                    <div class="info-label">Email пользователя</div>
                    <div class="info-value">{{ $user->email ?? '—' }}</div>
                </div>
                @endif
                <div class="info-item">
                    <div class="info-label">Контактное лицо</div>
                    <div class="info-value">{{ $orderData->contact_name ?? '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Телефон</div>
                    <div class="info-value">{{ $orderData->contact_phone ?? '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value">{{ $orderData->contact_email ?? '—' }}</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Адрес доставки</div>
            <div class="info-grid">
                <div class="info-item" style="grid-column: 1 / -1;">
                    <div class="info-label">Полный адрес</div>
                    <div class="info-value">{{ $address ? $address->full_address : 'Адрес не указан' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Тип доставки</div>
                    <div class="info-value">
                        @if($orderData->delivery_type === 'delivery')
                            Доставка курьером
                        @elseif($orderData->delivery_type === 'pickup')
                            Самовывоз
                        @else
                            {{ $orderData->delivery_type ?? '—' }}
                        @endif
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Локация доставки</div>
                    <div class="info-value">{{ $shippingLocation ? $shippingLocation->name : '—' }}</div>
                </div>
                @if($shippingMethod)
                <div class="info-item">
                    <div class="info-label">Служба доставки</div>
                    <div class="info-value">
                        @if($shippingMethod->carrier)
                            {{ $shippingMethod->carrier->name }} — 
                        @endif
                        {{ $shippingMethod->name }}
                    </div>
                </div>
                @endif
                @if($deliveryHandlingType)
                <div class="info-item">
                    <div class="info-label">Тип обработки</div>
                    <div class="info-value">{{ $deliveryHandlingType->name }}</div>
                </div>
                @endif
                @if($orderData->delivery_floor)
                <div class="info-item">
                    <div class="info-label">Этаж</div>
                    <div class="info-value">{{ $orderData->delivery_floor }}</div>
                </div>
                @endif
                <div class="info-item">
                    <div class="info-label">Требуется сборка</div>
                    <div class="info-value">{{ $orderData->requires_assembly ? 'Да' : 'Нет' }}</div>
                </div>
                @if($orderData->delivery_date)
                <div class="info-item">
                    <div class="info-label">Дата доставки</div>
                    <div class="info-value">{{ \Carbon\Carbon::parse($orderData->delivery_date)->format('d.m.Y') }}</div>
                </div>
                @endif
                @if($orderData->delivery_time)
                <div class="info-item">
                    <div class="info-label">Время доставки</div>
                    <div class="info-value">{{ $orderData->delivery_time }}</div>
                </div>
                @endif
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Состав заказа</div>
            <div style="margin-bottom: 15px; padding: 10px; background: #f5f5f5; border-radius: 5px;">
                <div style="display: flex; justify-content: space-between; font-size: 13px;">
                    <span><strong>Позиций:</strong> {{ count($items) }} шт.</span>
                    <span><strong>Общее количество товаров:</strong> {{ collect($items)->sum('quantity') }} шт.</span>
                    <span><strong>Общая стоимость товаров:</strong> {{ number_format($orderData->subtotal, 2, ',', ' ') }} ₽</span>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Товар</th>
                        <th class="text-center">Количество</th>
                        <th class="text-right">Цена за единицу</th>
                        <th class="text-right">Итого</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ isset($item['product']) && $item['product'] ? $item['product']->name : ('Товар удален' . (isset($item['product_id']) && $item['product_id'] ? ' (ID: ' . $item['product_id'] . ')' : '')) }}</td>
                        <td class="text-center">{{ $item['quantity'] }}</td>
                        <td class="text-right">{{ number_format($item['price'], 2, ',', ' ') }} ₽</td>
                        <td class="text-right">{{ number_format($item['total'], 2, ',', ' ') }} ₽</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        @if(!empty($additionalServices) && count($additionalServices) > 0)
        <div class="section">
            <div class="section-title">Дополнительные услуги</div>
            <table>
                <thead>
                    <tr>
                        <th>Услуга</th>
                        <th class="text-right">Стоимость</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($additionalServices as $service)
                    <tr>
                        <td>{{ $service->service_name ?? $service->name }}</td>
                        <td class="text-right">
                            @php
                                $priceType = $service->price_type ?? 'fixed';
                                $price = (float) ($service->price ?? 0);
                            @endphp
                            @if($priceType === 'custom')
                                По договоренности
                            @elseif($priceType === 'from')
                                от {{ number_format($price, 2, ',', ' ') }} ₽
                            @else
                                {{ number_format($price, 2, ',', ' ') }} ₽
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        
        <div class="section">
            <div class="section-title">Детализация стоимости</div>
            <div class="total-section">
                <div class="total-row" style="font-size: 16px; font-weight: bold; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 10px;">
                    <span>Товары (общая стоимость):</span>
                    <span>{{ number_format($orderData->subtotal, 2, ',', ' ') }} ₽</span>
                </div>
                <div class="total-row" style="font-size: 11px; color: #666; margin-bottom: 15px;">
                    <span>Количество позиций: {{ count($items) }} шт.</span>
                    <span>Общее количество товаров: {{ collect($items)->sum('quantity') }} шт.</span>
                </div>
                <div class="total-row">
                    <span>Доставка:</span>
                    <span>{{ number_format($orderData->delivery_cost ?? 0, 2, ',', ' ') }} ₽</span>
                </div>
                @if($orderData->delivery_type === 'delivery' && $shippingLocation)
                <div class="total-row" style="font-size: 10px; color: #666; padding-left: 20px;">
                    <span>Локация: {{ $shippingLocation->name }}</span>
                </div>
                @endif
                <div class="total-row">
                    <span>Сборка:</span>
                    <span>{{ number_format($orderData->assembly_cost ?? 0, 2, ',', ' ') }} ₽</span>
                </div>
                @if($orderData->requires_assembly)
                <div class="total-row" style="font-size: 10px; color: #666; padding-left: 20px;">
                    <span>Требуется сборка</span>
                </div>
                @endif
                @if($additionalServicesTotal > 0)
                <div class="total-row">
                    <span>Дополнительные услуги:</span>
                    <span>{{ number_format($additionalServicesTotal, 2, ',', ' ') }} ₽</span>
                </div>
                <div class="total-row" style="font-size: 10px; color: #666; padding-left: 20px;">
                    <span>Добавлено услуг: {{ count($additionalServices) }}</span>
                </div>
                @else
                <div class="total-row" style="font-size: 10px; color: #666;">
                    <span>Дополнительные услуги: не добавлены</span>
                </div>
                @endif
                <div class="total-row final">
                    <span>ИТОГО К ОПЛАТЕ:</span>
                    <span>{{ number_format($orderData->total, 2, ',', ' ') }} ₽</span>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Оплата</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Способ оплаты</div>
                    <div class="info-value">
                        @switch($orderData->payment_method)
                            @case('card') Карта @break
                            @case('cash') Наличные @break
                            @case('installment') Рассрочка @break
                            @default {{ $orderData->payment_method ?? '—' }}
                        @endswitch
                    </div>
                </div>
            </div>
        </div>
        
        @if(!empty($statusHistoryData) && count($statusHistoryData) > 0)
        <div class="section">
            <div class="section-title">История статусов</div>
            <div class="status-history">
                @foreach($statusHistoryData as $history)
                <div class="status-item">
                    <div class="info-value">
                        <strong>
                            @php
                                $historyStatusValue = (string) ($history['status'] ?? '');
                                $historyStatusLabel = match ($historyStatusValue) {
                                    'new' => 'Новый',
                                    'accepted' => 'Принят',
                                    'assembled' => 'Собран',
                                    'shipped' => 'Отправлен',
                                    'in_transit' => 'В пути',
                                    'delivered' => 'Доставлен',
                                    'cancelled' => 'Отменен',
                                    default => $historyStatusValue,
                                };
                            @endphp
                            {{ $historyStatusLabel }}
                        </strong>
                    </div>
                    <div class="status-date">
                        {{ $history['created_at']->format('d.m.Y H:i:s') }}
                        @if($history['user'])
                            — {{ $history['user']->name }}
                        @else
                            — Система
                        @endif
                        @if($history['comment'])
                            — {{ $history['comment'] }}
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        
        @if($orderData->comment)
        <div class="section">
            <div class="section-title">Комментарий к заказу</div>
            <div class="info-value" style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                {{ $orderData->comment }}
            </div>
        </div>
        @endif
        
        <div class="footer">
            <p>Документ сформирован {{ now()->format('d.m.Y H:i') }}</p>
            <p>Заказ № {{ $orderData->number }}</p>
        </div>
    </div>
</body>
</html>
