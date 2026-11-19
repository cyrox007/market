<!doctype html>
<html lang="ru">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title>Тестовая оплата</title>
<style>
body{font:16px system-ui;background:#f5f5f5;color:#141414;margin:0;padding:24px}main{max-width:600px;margin:40px auto;background:white;padding:32px;border-radius:20px}button,a{font:inherit}button{display:block;width:100%;padding:14px;margin:10px 0;border:1px solid #ddd;border-radius:10px;cursor:pointer;background:#fff}button:first-of-type{background:#ffdb00}small{color:#666}
</style>
<main>
<h1>Тестовая оплата</h1>
<p><strong>Имитатор банка. Деньги не списываются.</strong></p>
<p>Заказ {{ $bank->order_number }} · {{ number_format($bank->amount / 100, 2, ',', ' ') }} ₽</p>
<p>Статус имитатора: {{ $bank->unreachable ? 'нет ответа' : $bank->status }}</p>
<form method="post" action="{{ $action }}">
@csrf
<button name="scenario" value="success">Успешная оплата</button>
<button name="scenario" value="decline">Банк отклонил платёж</button>
<button name="scenario" value="cancel">Покупатель отменил оплату</button>
<button name="scenario" value="timeout">Банк не отвечает</button>
<button name="scenario" value="notify">Повторить уведомление</button>
<button name="scenario" value="refund-check">Проверить возврат отменённого заказа</button>
</form>
<p><a href="{{ $back }}">Вернуться к заказу</a></p>
<small>После выбора магазин проверит статус через обычный обработчик платежа. Для поздней оплаты сначала отмените заказ на сайте, затем вернитесь сюда. Возврат требует включённых локальных флагов автоматического возврата.</small>
</main>
</html>
