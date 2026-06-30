# Паттерн «Шлюзы» и логирование

## Идея

Внешние интеграции (оплата, доставка, склад и т.д.) оформляются как **шлюзы (gateways)**. Каждое взаимодействие с шлюзом пишется в **единый лог** — так видно, кто, когда и что оплатил/отправил, и можно искать по заказам и датам.

## Компоненты

### 1. Контракт логгера

**`App\Contracts\Gateway\GatewayLoggerInterface`**

- Один интерфейс для всех шлюзов.
- Метод `log($gateway, $action, $message, $meta, $loggable, $channel, $level)`.
- `gateway` — идентификатор шлюза (`raiffeisen_acquiring`, `raiffeisen_ecom`, в будущем например `cdek`, `boxberry`).
- `channel` — канал: `payment`, `delivery` и т.д.
- `action` — что произошло: `payment_initiated`, `callback_success`, `callback_failed` и т.д.
- `meta` — массив контекста: `order_id`, `order_number`, `amount`, `contact_email`, `ip` и т.д.
- `loggable` — опционально связанная модель (Payment, Order).

### 2. Реализация логгера

**`App\Services\Gateway\DatabaseGatewayLogger`**

- Пишет в таблицу `gateway_logs`.
- Регистрируется в `AppServiceProvider` как `GatewayLoggerInterface`.
- Из `meta['order_id']` заполняет `order_id` для быстрой связи с заказом и фильтрации.

### 3. Таблица `gateway_logs`

- `gateway`, `channel`, `action` — кто и что.
- `order_id` — заказ (для списков и фильтров).
- `loggable_type`, `loggable_id` — полиморфная связь (платёж, заказ и т.д.).
- `message` — человекочитаемый текст.
- `level` — `info`, `warning`, `error`.
- `meta` (JSON) — полный контекст: сумма, email, IP, remote_id и т.д.
- `created_at` — время события.

### 4. Использование в коде

- **OrderController**: после создания платежа вызывается  
  `$this->gatewayLog->log(..., 'payment_initiated', ..., $payment)` с данными заказа и суммой.
- **ProcessRaiffeisenCallbackJob** / **ProcessRaiffeisenEcomCallbackJob**: при получении callback — `callback_received`, после обработки — `callback_success` или `callback_failed`, с суммой, email, IP из payload.
- **RaiffeisenEcomClient**: при вызове API создания заказа — `order_created` или `order_create_failed`.
- В callback в Job передаётся IP запроса (`_request_ip` из контроллера) и пишется в `meta` и лог.

### 5. Админка (Filament)

- **Ресурс «Логи шлюзов»** (`GatewayLogResource`) — список всех записей с фильтрами по шлюзу, каналу, действию, уровню, с переходом к заказу.
- В карточке заказа — **RelationManager «Логи шлюзов»**: только логи по этому заказу (оплата, callback и т.д.).

## Расширение на доставку и др.

1. Добавить шлюз доставки (например, класс `CdekDeliveryGateway`).
2. В местах, где вызывается API доставки или приходит webhook, инжектить `GatewayLoggerInterface` и вызывать:
   - `$gatewayLog->log('cdek', 'label_created', 'Создана накладная', [...], $shipment, 'delivery', 'info');`
3. В логах появятся записи с `channel = delivery`; фильтр в админке по каналу уже поддерживается.

## Лучшие практики Laravel

- **Инъекция контракта**: в контроллерах и Job используется `GatewayLoggerInterface`, реализация подставляется контейнером.
- **Единая точка логирования**: все шлюзы пишут в одну таблицу и один контракт — проще аналитика и поиск.
- **Очереди**: тяжёлая обработка callback в Job, в лог пишется уже из Job с полным контекстом.
- **Модель и полиморфная связь**: `GatewayLog` связан с `Order` через `order_id` и с сущностями через `loggable` — удобно строить отчёты и связи в Filament.
