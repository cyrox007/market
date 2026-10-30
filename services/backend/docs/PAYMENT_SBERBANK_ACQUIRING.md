# Эквайринг Сбербанка (e-commerce API)

## Переменные окружения (тест)

```env
SBERBANK_ACQUIRING_USERNAME=your_test_merchant_login
SBERBANK_ACQUIRING_PASSWORD="set-via-secret-management"
SBERBANK_ACQUIRING_BASE_URL=https://ecomtest.sberbank.ru
SBERBANK_ACQUIRING_API_FORMAT=ecom
APP_FRONTEND_URL=https://demo.site.zone
APP_URL=https://demo1.site.zone
```

Пароль в `.env` **обязательно в кавычках**, если содержит `%` или спецсимволы.

После изменений: `php artisan config:clear`

## Промышленная среда

Используйте креды из ЛК (userName / merchantLogin совпадают, например `P_4823025960%`).

1. Смените временный пароль на постоянный (мин. 18 символов, цифра + заглавная латиница):  
   https://www.sberbank.ru/help/business/sbbol/100667  
   API смены: https://ecomtest.sberbank.ru/doc#tag/changePasswordServices

2. Укажите в `.env` боевой URL: `SBERBANK_ACQUIRING_BASE_URL=https://epay.sberbank.ru` (по документации Сбера).

3. `SBERBANK_ACQUIRING_VERIFY_SSL=true`

## Callback

URL для уведомлений (на backend):

`https://<backend-domain>/api/v1/payment/sberbank/callback`

Укажите в `.env` (опционально, для dynamicCallbackUrl при register):

```env
SBERBANK_ACQUIRING_CALLBACK_URL=https://<backend-domain>/api/v1/payment/sberbank/callback
```

Тот же URL — в настройках мерчанта в ЛК Сбера. Обработчик: `ProcessSberbankCallbackJob` (как у Райффайзен).

Return/Fail URL задаются автоматически:  
`APP_FRONTEND_URL/orders/{id}?payment=success|fail`

## Логи

В админке: **Заказы → Логи шлюзов** (`gateway = sberbank_acquiring`).

Ключевые действия: `order_register_initiated`, `order_register_success`, `order_register_failed`, `payment_initiated`, `callback_*`.

## Типичные ошибки

| errorCode | Значение |
|-----------|----------|
| 5 | Доступ запрещён — неверный логин/пароль или IP не в белом списке у Сбера |
| cURL 60 | Нет доверия к SSL в контейнере — обновить CA или временно `SBERBANK_ACQUIRING_VERIFY_SSL=false` (только local) |

## Документация Сбера

https://ecomtest.sberbank.ru/doc


## Подтверждение оплаты и денежные единицы

Callback служит сигналом: платёж НЕ отмечается оплаченным по полям входящего уведомления.
`ProcessSberbankCallbackJob` делает независимый запрос `getOrderStatusExtended.do`,
сверяет банковский `orderStatus=2`, `errorCode=0` и точную сумму заказа.
Банк возвращает сумму в **копейках**, `amount_paid` в базе хранится в **рублях**.
Например, `amount:123456` → `amount_paid:1234.56`.
Неполный, неизвестный, ошибочный и ожидающий статус не создаёт оплаченную сумму.

## Автоматический возврат после отмены (строго opt-in)

По умолчанию **не включён**, даже если возвращения Райффайзена активны.
Для активации ПОСЛЕ реальных тестов с мерчантом потребуются оба флага:

```env
ORDERS_AUTOMATIC_CANCEL_REFUNDS=true
ORDERS_SBERBANK_AUTO_REFUNDS=true
```

Возврат поддерживается только для полностью подтверждённой оплаченной транзакции
при отменённом заказе. Вызывается `refund.do` с суммой **в копейках**,
и только после сохранения неизменяемой записи `payment_refunds`.
Повторный POST **никогда не выполняется** при сетевой ошибке, рестарте процесса
или неясном ответе банка. Сверка использует `getOrderStatusExtended.do`;
лишь подтверждённый банком `orderStatus=4` переводит возврат в `completed`.
В остальных случаях задача остаётся для проверки. Не делать ручной возврат
до сверки в банковском ЛК — опасность двойного перечисления.

Банковский ключ идемпотентности документирован с ограниченным сроком действия
и не должен рассматриваться как достаточная защита от повторного возврата.
Перед включением необходимо проверить параметры фискализации/чеков и наличие
доступа мерчанта к `refund.do` именно в его ecom-контуре.

Если автоперечисление не включено, оплаченный отменённый заказ продолжает
создавать обычную задачу `manual_review` — бизнес-процесс не блокируется.
