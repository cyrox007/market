@if(filled($customBody))
{!! \App\Services\Mail\MailTemplateContent::plainText((string) $customBody) !!}
@else
Ваш заказ № {{ $order->number }} создан.
Сумма заказа: {{ number_format((float) $order->total, 2, ',', ' ') }} ₽.
@endif

@if($orderAccessUrl)
Открыть заказ без регистрации:
{!! $orderAccessUrl !!}

Ссылка действует 30 дней с даты создания заказа. Она открывает доступ к вашему заказу — не передавайте её посторонним.
@endif
