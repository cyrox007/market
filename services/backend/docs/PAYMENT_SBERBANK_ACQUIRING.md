# Эквайринг Сбербанка (e-commerce API)

## Переменные окружения (тест)

```env
SBERBANK_ACQUIRING_USERNAME=testMerchant_071
SBERBANK_ACQUIRING_PASSWORD="Sbertest2026123456%"
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
