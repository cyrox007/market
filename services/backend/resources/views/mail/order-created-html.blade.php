<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Информация о заказе</title>
</head>
<body style="margin:0;padding:24px;background:#f7f7f7;color:#222;font-family:Arial,sans-serif;line-height:1.5">
<main style="max-width:620px;margin:auto;padding:24px;background:#fff;border-radius:8px">
    <h1 style="font-size:22px">Светофор мебели</h1>
    @if(filled($customBody))
        {!! \App\Services\Mail\MailTemplateContent::html((string) $customBody) !!}
    @else
        <p>Ваш заказ № {{ $order->number }} создан.</p>
        <p>Сумма заказа: {{ number_format((float) $order->total, 2, ',', ' ') }} ₽.</p>
    @endif
    @if($orderAccessUrl)
        <hr style="border:0;border-top:1px solid #ddd;margin:24px 0">
        <p><strong>Откройте ваш заказ без регистрации</strong></p>
        <p><a href="{{ $orderAccessUrl }}">Перейти к заказу №{{ $order->number }}</a></p>
        <p style="font-size:13px;color:#555">Ссылка действует 30 дней с даты создания заказа и предоставляет доступ к заказу. Не пересылайте её другим людям.</p>
    @endif
</main>
</body>
</html>
