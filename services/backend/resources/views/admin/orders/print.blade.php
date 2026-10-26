<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><title>Заказ {{ $order->number }}</title>
<style>body{font:14px/1.5 Arial,sans-serif;max-width:1000px;margin:32px auto;color:#202020}h1{font-size:24px}h2{font-size:18px;margin-top:28px}table{width:100%;border-collapse:collapse}td,th{padding:10px;border-bottom:1px solid #ddd;text-align:left}.total{font-size:18px;font-weight:bold}small{color:#555}@media print{button{display:none}body{margin:0;max-width:none}tr{break-inside:avoid}}</style></head>
<body>
<button type="button" onclick="window.print()">Распечатать</button>
<h1>Заказ {{ $order->number }}</h1>
<p>{{ $order->created_at?->format('d.m.Y H:i') }} · {{ $order->getStatusLabel() }} · {{ $operator->paymentLabel($order) }}</p>
<h2>Покупатель</h2>
<p>{{ $order->contact_name }}<br>{{ $order->contact_phone }}<br>{{ $order->contact_email }}</p>
<h2>{{ $order->delivery_type === 'pickup' ? 'Самовывоз' : 'Доставка' }}</h2>
<p>Склад / точка выдачи: {{ $operator->warehouse($order) }}<br>Адрес: {{ $operator->address($order) }}</p>
@if($order->delivery_floor !== null)<p>Этаж: {{ $order->delivery_floor }}</p>@endif
@if($order->delivery_date)<p>Дата: {{ $order->delivery_date->format('d.m.Y') }} {{ $order->delivery_time }}</p>@endif
<h2>Товары</h2>
<table><thead><tr><th>Товар</th><th>Артикул</th><th>Количество</th><th>Цена</th><th>Сумма</th></tr></thead><tbody>
@foreach($order->items as $item)<tr><td>{{ $item->name ?: $item->product?->name }}</td><td>{{ $item->product?->sku }}</td><td>{{ $item->quantity }}</td><td>{{ number_format((float)$item->price,2,',',' ') }} ₽</td><td>{{ number_format((float)$item->total,2,',',' ') }} ₽</td></tr>@endforeach
</tbody></table>
<h2>Стоимость и услуги</h2>
<table><tbody>
@foreach(['Товары'=>(float)$order->subtotal,'Доставка'=>max(0,(float)$order->delivery_cost-(float)($order->fulfillment_snapshot['handling_cost']??0)),'Подъём и разгрузка'=>(float)($order->fulfillment_snapshot['handling_cost']??0),'Сборка'=>(float)$order->assembly_cost,'Дополнительные услуги'=>(float)($order->fulfillment_snapshot['additional_services_cost']??0)] as $label=>$amount)
<tr><td>{{ $label }}</td><td>{{ number_format($amount,2,',',' ') }} ₽</td></tr>
@endforeach
<tr class="total"><td>Итого</td><td>{{ number_format((float)$order->total,2,',',' ') }} ₽</td></tr>
</tbody></table>
@foreach($order->fulfillment_snapshot['additional_services']??[] as $service)<p>{{ $service['name'] }} — {{ number_format((float)$service['price'],2,',',' ') }} ₽</p>@endforeach
@if($order->comment)<h2>Комментарий</h2><p>{{ $order->comment }}</p>@endif
<small>Адрес, склад и суммы сохранены при оформлении заказа.</small>
</body></html>