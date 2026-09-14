# Синхронизация заказов market → integration API / 1С

## Поток данных

1. Покупатель создаёт заказ в Laravel API market.
2. `OrderCreated` ставит `SyncOrderTo1CJob` в очередь `integration-1c`.
3. Изменение статуса заказа также повторно ставит этот заказ в ту же очередь.
4. Job собирает актуальный снимок заказа и отправляет его в integration API.

Интеграция является server-to-server. Секрет не должен попадать в React/Vite frontend.

## Текущий compatibility-контракт

По умолчанию market использует:

- `POST {ONEC_API_BASE_URL}/api/v1/integration/1c/orders`;
- body `{ "orders": [...] }`;
- `Authorization: Bearer <ONEC_API_KEY>`.

Этот legacy контракт должен сохраняться до координированного переключения market и `api.svetofor` на канонический `/api/v2/orders/sync`.

## Переменные окружения

```dotenv
ONEC_API_ENABLED=true
ONEC_API_BASE_URL=https://integration-api.example.com
ONEC_API_KEY=<server-secret>
ONEC_API_TIMEOUT=30
ONEC_ORDERS_PATH=/api/v1/integration/1c/orders
ONEC_ORDERS_QUEUE=integration-1c
```

Не коммитьте реальный `ONEC_API_KEY` в Git.

## Безопасная диагностика перед smoke

До отправки реальных заказов выполните read-only проверку:

```bash
php artisan integration:1c:doctor
```

Команда показывает:

- включена ли интеграция;
- итоговый order endpoint;
- задан ли API key (только факт наличия, значение секрета не выводится);
- `QUEUE_CONNECTION` и фактический queue driver;
- имя очереди заказов;
- driver для failed jobs.

Команда ничего не отправляет во внешнее API и не ставит заказы в очередь. Если отсутствуют обязательные `ONEC_API_*`, она завершается с кодом ошибки.

Не используйте `orders:enqueue-1c-sync` как read-only проверку: эта команда действительно ставит найденные заказы на отправку.

## Queue worker

`SyncOrderTo1CJob` реализует `ShouldQueue`, поэтому включённая интеграция требует постоянно работающего queue worker. Репозиторный Docker Compose запускает очередь так:

```bash
php artisan queue:work --queue=integration-1c,default --tries=3 --backoff=20 --timeout=60
```

Проверка перед релизом:

```bash
php artisan integration:1c:doctor
php artisan queue:failed
php artisan queue:monitor integration-1c:100
```

Если HTTP-синхронизация вернула ошибку, job теперь бросает исключение и Laravel выполняет retry вместо ложного успешного завершения.

## Самовывоз

Текущий market payload содержит `pickFromStore=true`, но пока не содержит стабильный `storeExternalId` из домена `api.svetofor`. Нельзя подставлять локальный `shipping_location_id`, название или `code` без подтверждённого mapping: это другая сущность.

На переходный период legacy endpoint API должен принимать такой заказ и хранить `storeExternalId=null`. Канонический `/api/v2/orders/sync` должен оставаться строгим и требовать реальный `storeExternalId`.

Перед переключением market на `/api/v2/orders/sync` необходимо добавить явное сопоставление точки самовывоза market → `Store.externalId` API.

## Staging gate

Перед production включением `ONEC_API_ENABLED=true` проверить:

1. `php artisan integration:1c:doctor` завершается успешно;
2. queue worker действительно запущен и слушает `integration-1c`;
3. `ONEC_API_BASE_URL` указывает на нужное окружение API;
4. секрет `ONEC_API_KEY` совпадает с `API_TOKEN` на API;
5. обычный delivery-заказ появляется в API;
6. изменение статуса обновляет тот же `orderId`, а не создаёт дубль;
7. legacy pickup-заказ не теряется;
8. временная недоступность API приводит к retry и затем к записи в failed jobs после исчерпания попыток;
9. в логах нет повторяющихся 401/422/5xx.
