# Критичные эндпоинты API

Документ описывает бизнес-критичные эндпоинты публичного API (`/api/v1`) — те, отказ или дефект которых напрямую ломает деньги (заказ, оплата, стоимость доставки) или доступ (вход, каталог). Составлен по фактическому коду `routes/api.php` и контроллеров. Дополняет OpenAPI-спеку (`public/api-docs/openapi.json`), которая покрывает все 81 операцию публичного API (100% маршрутов), выверена по коду ветки main и проверена живыми вызовами.

Формат спеки описывает контракт; этот документ дополняет его тем, что в OpenAPI не укладывается: критичность, побочные эффекты, кэш, риски и сквозные аспекты.

## Оговорки по формулировке задачи

Исходный список доменов — «auth, products, categories, cart, checkout, orders, users». Соответствие реальной структуре:

- **`users`** — отдельного домена в API нет. Функции пользователя распределены: профиль внутри `auth/*`, адреса — `/addresses`, управление пользователями и ролями — только в Filament-админке (`/admin_sv`).
- **`checkout`** — не эндпоинт, а сценарий из нескольких вызовов (см. раздел «Сценарий checkout»).
- Домены, которых нет в исходном списке, но которые критичны для денег: **платежи и колбэки**, **доставка (shipping)**, **регионы**.

## Модель аутентификации

Гибридная, это ключевой момент для всех защищённых эндпоинтов:

| Middleware-группа | Что даёт | Где применяется |
|---|---|---|
| _(нет)_ | Публичный доступ | Каталог, категории, магазины, регионы, справочники доставки/оплаты |
| `api-session` | Сессия + шифрованные куки, **без обязательного логина** | Корзина, wishlist, compare, **создание заказа** |
| `api-session` + `auth:sanctum` | Требуется аутентификация | Личный кабинет, адреса, история заказов |

Аутентификация **сессионная (cookie-based)**, а не токен в теле ответа: `register`/`login` вызывают `Auth::login()` и полагаются на сессионную куку `svetofor-session`. Гость и авторизованный пользователь ходят через один механизм; заказ можно создать без логина (см. `POST /orders`).

**⚠️ Побочный эффект авторегистрации при заказе.** `POST /orders` без логина при указании email существующего пользователя выполняет `Auth::login($existingUser)` — то есть авторизует текущую сессию под этим аккаунтом **без проверки пароля** (`OrderController::store`, ветка «пользователь существует»). Практически: гость, указавший чужой email при оформлении, получает сессию владельца этого email и доступ к его личному кабинету (история заказов, адреса). Подробнее и план устранения — риск Р-2 в разделе «Риски безопасности и план устранения».

---

## 1. auth — аутентификация

Контроллер: `App\Http\Controllers\Api\AuthController` (586 строк).

| Метод | Путь | Доступ | Критичность |
|---|---|---|---|
| POST | `/auth/register` | session | Высокая |
| POST | `/auth/login` | session | Высокая |
| POST | `/auth/password/forgot` | session | Средняя |
| POST | `/auth/password/reset` | session | Средняя |
| POST | `/auth/logout` | sanctum | Средняя |
| GET | `/auth/me` | sanctum | Средняя |
| PUT | `/auth/profile` | sanctum | Низкая |
| PUT | `/auth/password` | sanctum | Средняя |
| GET/PUT | `/auth/notification-settings` | sanctum | Низкая |

**`POST /auth/register`** — валидация: `name` required|max:255, `email` required|email|max:255|unique:users, `password` required|min:8|confirmed (нужно `password_confirmation`), `phone` nullable|max:50. Пароль хэшируется через cast `hashed`. Побочный эффект: создание пользователя + автологин (`Auth::login`). Ответ: `201` с объектом `user` (без пароля/токена).

**`POST /auth/login`** — валидация: `email` required|email, `password` required. Проверка через `Hash::check`, при неудаче — 422 с `errors.email` = «Неверный email или пароль». Аутентификация сессионная (не токен в ответе).

**`POST /auth/password/forgot`** — валидация: `email` required|email. Использует `Password::sendResetLink`. Ответ **всегда** `200` с обобщённым сообщением «If your email exists…» — существование email не раскрывается (это правильно). **`/reset`** — `token`, `email`, `password` (min:8|confirmed).

**Риски:**
- **Нет rate limiting.** `throttle`/`RateLimiter` в проекте не используются нигде (проверено grep по `app/`, `routes/`, `bootstrap/`). `login`, `register`, `password/forgot` открыты для перебора и спама.
- `register` создаёт пользователя без подтверждения email → регистрация мусорных аккаунтов.
- `password/forgot` без throttle — вектор рассылки писем на произвольные адреса (сообщение обобщённое, но письмо реально уходит).

---

## 2. products — каталог

Контроллер: `App\Http\Controllers\Api\ProductController` (1653 строки). Один из двух самых нагруженных доменов.

| Метод | Путь | Доступ | Критичность |
|---|---|---|---|
| GET | `/products` | public | **Критичная** |
| GET | `/products/{slug}` | public | **Критичная** |
| GET | `/products/search` | public | Высокая |
| GET | `/products/featured` | public | Высокая |
| GET | `/products/new` | public | Средняя |
| GET | `/products/sale` | public | Средняя |
| GET | `/products/collections/{slug}` | public | Средняя |
| GET | `/products/{id}/bundle` | public | Средняя |
| GET | `/products/{id}/related` | public | Низкая |

**`GET /products`** — query-параметры: `per_page` (по умолчанию 20, максимум 100), `page`, `sort_by` (по умолчанию `created_at`), `sort_order` (`desc`), `category_id`, `category_slug`, `price_min`, `price_max`, `search`, `attributes[slug][]` (фильтры-атрибуты), `shipping_location_id` (приоритетный параметр локации), `region_id` (legacy fallback).

Ответ — Laravel-пагинатор: `{ data: [...], links, meta }`. В каждом товаре ~30 полей (ветка main; поля сверены с `ProductResource` и живым API), включая специфичные: `is_visible_in_region`, `is_variable`, `is_variant`, `first_available_variant_id`, `image_hd`/`image_fullhd`, `backorder`, `stocks` (остатки по складам), `seo`, `in_stock`, `default_color`/`default_size`, `discount_percent`, `delivery_days`. Поля `category`/`categories` — условные (при загруженных `taxons`). Примечание: в списках `sku`/`image_hd`/`image_fullhd` = `null` (заполнены только в detail). На демо-стенде `demo1.site.zone` набор отличается (есть `excerpt`, нет `backorder`/`stocks`) — стенд отстаёт от main.

**Региональность (критично).** Видимость товаров по регионам применяется server-side, чтобы `total` и пагинация совпадали с фактическим списком (см. корневой README). Цена и `is_visible_in_region` подставляются в ресурсе по `request`. Из-за этого при описании контракта нельзя игнорировать `shipping_location_id` — он меняет содержимое ответа.

**Кэш.** Сегментация hot/cold (`config/cache_segments.php`): «горячие» ключи (первые страницы, категории, блоки главной) — TTL 24 ч, остальные — 10 ч. Кэш **не привязан к региону** (один ключ на все регионы, регионозависимые поля подставляются при отдаче). Прогрев — командой `cache:warm-hot` (по расписанию, ежечасно). Инвалидация каталожных cache-tags (`product_index_cache`, `product_search_cache`) — при изменении `ProductRegionRule`.

**Риски:**
- `per_page` ограничен 100 — защита от чрезмерной выборки есть.
- Тяжёлые ответы: `categories/tree` и подобные — сотни КБ; без пагинации.

---

## 3. categories — категории

Контроллер: `App\Http\Controllers\Api\CategoryController`.

| Метод | Путь | Доступ | Критичность |
|---|---|---|---|
| GET | `/categories` | public | Высокая |
| GET | `/categories/tree` | public | Высокая |
| GET | `/categories/{slug}` | public | **Критичная** |

`/categories/tree` участвует в SSR главной страницы; на живом стенде отдаёт ~515 КБ. `/categories/{slug}` — вход в листинг категории, дальше подключается фильтрация из `/products`. Кэш — та же hot/cold-сегментация.

---

## 4. cart — корзина

Контроллер: `App\Http\Controllers\Api\CartController` (507 строк). Доступ: `api-session` (гостевая корзина в сессии).

| Метод | Путь | Доступ | Критичность |
|---|---|---|---|
| GET | `/cart` | session | Высокая |
| POST | `/cart` | session | Высокая |
| GET | `/cart/count` | session | Низкая |
| PUT | `/cart/{itemId}` | session | Средняя |
| DELETE | `/cart/{itemId}` | session | Средняя |
| DELETE | `/cart` | session | Низкая |

**`POST /cart`** — валидация: `product_id` required|exists:products, `quantity` sometimes|integer|min:1 (по умолчанию 1), `variation_attributes[].attribute_slug` / `.value_slug` (для вариативных товаров). Для вариативного товара по атрибутам резолвится конкретный вариант (`getVariantByVariationAttributes`); если вариант не найден — ошибка. Проверяется наличие на складе.

**Важно для контракта.** Корзина живёт на сервере в сессии (`Vanilo\Cart`), а не в теле запроса. Это определяет поведение заказа: `POST /orders` берёт позиции из серверной корзины, а не из переданного списка.

---

## 5. Сценарий checkout

Отдельного эндпоинта нет. Оформление — последовательность вызовов, каждый из которых критичен для корректной суммы:

1. `GET /shipping/locations` (или `/locations/tree`) — выбор локации доставки.
2. `POST /shipping/calculate` — расчёт по локации.
3. `GET /shipping/shipping-methods` → `POST /shipping/shipping-methods/calculate` — способ и стоимость доставки.
4. `GET /shipping/additional-services`, `/delivery-handling-types` — доп. услуги (сборка, подъём).
5. `GET /payment-methods` — доступные в регионе способы оплаты.
6. `POST /orders` — создание заказа.
7. `GET /orders/{id}/payment-config` — конфигурация онлайн-оплаты.
8. Редирект на платёжный шлюз → банк шлёт колбэк (см. раздел «Платежи»).

### shipping — доставка

Контроллер: `App\Http\Controllers\Api\ShippingController`. Слой расчёта — `app/Services/Shipping/` (калькуляторы, провайдеры за интерфейсами).

| Метод | Путь | Доступ | Критичность |
|---|---|---|---|
| GET | `/shipping/locations` | public | Высокая |
| GET | `/shipping/locations/tree` | public | Средняя |
| GET | `/shipping/locations/{locationId}` | public | Средняя |
| POST | `/shipping/calculate` | public | **Критичная** |
| POST | `/shipping/shipping-methods/calculate` | public | **Критичная** |
| GET | `/shipping/shipping-methods` | public | Высокая |
| GET | `/shipping/carriers` | public | Средняя |
| GET | `/shipping/additional-services` | public | Средняя |
| GET | `/shipping/delivery-handling-types` | public | Средняя |

Критичность `/shipping/*/calculate` — прямая: ошибка = неверная сумма в заказе = финансовый убыток на каждой продаже.

### payment-methods

| Метод | Путь | Доступ | Критичность |
|---|---|---|---|
| GET | `/payment-methods` | public | Высокая |

Региональная доступность (`RegionPaymentMethod`, `PaymentMethodAvailabilityService`). Отдаст метод, недоступный в регионе, — клиент упрётся в ошибку на этапе оплаты.

---

## 6. orders — заказы

Контроллер: `App\Http\Controllers\Api\OrderController` (1155 строк).

| Метод | Путь | Доступ | Критичность |
|---|---|---|---|
| POST | `/orders` | **session (без логина)** | **Критичная** |
| GET | `/orders/{id}/payment-config` | session / sanctum | **Критичная** |
| GET | `/orders` | sanctum | Средняя |
| GET | `/orders/{id}` | sanctum | Высокая |
| POST | `/orders/{id}/cancel` | sanctum | Средняя |
| POST | `/orders/{id}/repeat` | sanctum | Низкая |

**`POST /orders`** — создание заказа **без аутентификации** (только сессия). Валидация (обязательные): `contact_name` (max:255), `contact_phone` (max:20), `contact_email` (email, max:255), **`payment_method`** (string, проверяется на существование активного метода по `code`), `delivery_type` (in:delivery,pickup). Опциональные: `shipping_location_id`/`region_id` (exists:shipping_locations), `shipping_method_id` (integer), `delivery_handling_type_id` (exists), `delivery_floor` (1–20), `requires_assembly` (boolean), `additional_services[]` (`.id` exists, `.price` numeric), `delivery_date` (date)/`delivery_time`, `address_id` (exists:user_addresses) или вложенный `address{city,street,house,apartment,entrance}` (city/street/house — `required_with:address`), `delivery_cost`/`assembly_cost` (numeric|min:0), `comment` (max:1000).

Последовательность обработки: проверка остатков (`ValidateStockAction`) → авторегистрация/логин пользователя → расчёт доставки → создание заказа в транзакции. Ответ: `201`; для онлайн-оплаты (`raiffeisen_acquiring`/`raiffeisen_ecom`/`sberbank_acquiring`) начальный статус `AWAITING_PAYMENT`, иначе — `ACCEPTED`.

Позиции заказа и `subtotal` берутся из серверной корзины (`Cart::getItems()`, `Cart::total()`), **не из тела запроса** — их подменить нельзя.

**Автосоздание/логин пользователя** (`tests/Feature/Api/OrderControllerAutoRegistrationTest`): если гость и email свободен — создаётся пользователь со случайным паролем + `event(UserAutoRegistered)`; **если email уже занят — выполняется `Auth::login($existingUser)` без проверки пароля** (см. находку про account takeover в разделе аутентификации).

**`GET /orders/{id}/payment-config`** — объявлен в двух группах (публичной и защищённой). Проверка доступа реализована в контроллере (`OrderController::paymentConfig`):
- авторизованный: `order.user_id === user.id` **или** совпадение `contact_email`;
- гость: `session('last_created_order_id') === order.id` (одноразово, ключ сбрасывается).

Прямой перебор по числовому `id` без сессии/логина отсекается (403). Корректно, но держать в поле зрения при рефакторинге.

**`GET /orders/{id}`, `POST /orders/{id}/cancel`, `/repeat`** — под `auth:sanctum`; владение проверяется (`order.user_id === user.id` либо совпадение `contact_email` для гостевых заказов). IDOR закрыт. `GET /orders` фильтрует по `user_id` и отдаёт список без пагинации (`->get()`).

**Риски:**
- `POST /orders` публичный и без throttle → спам-заказы, нагрузка на очереди и почту.
- **`delivery_cost`/`assembly_cost` доверяются клиенту.** Если поле передано, берётся именно оно: `$finalDeliveryCost = $validated['delivery_cost'] ?? $deliveryCost` (исключение — `pickup`, там форсируется 0). При этом комментарий в коде утверждает «клиент не может подменить расчёт через delivery_cost без согласования» — поведение кода противоречит комментарию. `subtotal` защищён (из корзины), но итоговая доставка/сборка — нет.

---

## 7. users → auth/profile + addresses

Публичного CRUD пользователей нет. Персональные данные:

### addresses (sanctum)

| Метод | Путь | Доступ | Критичность |
|---|---|---|---|
| GET | `/addresses` | sanctum | Средняя |
| POST | `/addresses` | sanctum | Средняя |
| GET | `/addresses/{address}` | sanctum | Высокая |
| PUT | `/addresses/{address}` | sanctum | Средняя |
| DELETE | `/addresses/{address}` | sanctum | Средняя |
| POST | `/addresses/{address}/set-default` | sanctum | Низкая |

Проверка владения реализована: `show`/`update`/`destroy`/`setDefault` сверяют `address->user_id !== $user->id` и возвращают `403 Forbidden`; `index` фильтрует по `user_id`. IDOR закрыт. Валидация полей адреса — в контроллере (`city`/`street`/`house` и т.п.).

Управление пользователями и ролями — Filament-админка (`/admin_sv`), spatie/permission.

---

## 8. Платежи и колбэки — самое критичное в системе

Три неаутентифицированных эндпоинта, меняющих статус оплаты заказа. По критичности выше всего каталога: поддельный «оплачено» = отгрузка бесплатно.

| Метод | Путь | Доступ | Проверка подписи |
|---|---|---|---|
| POST | `/payment/raiffeisen/callback` (эквайринг) | public | **⚠️ Отсутствует** |
| POST | `/payment/raiffeisen-ecom/callback` | public | **Есть** (`verifyPaymentSignature`) |
| POST | `/payment/sberbank/callback` | public | **⚠️ Отсутствует** |
| GET | `/payment/raiffeisen/config-check` | public, только `APP_DEBUG` | — |

Все три колбэка сразу отвечают `200 OK` и обрабатывают payload асинхронно в джобе (`ProcessRaiffeisenCallbackJob`, `ProcessRaiffeisenEcomCallbackJob`, `ProcessSberbankCallbackJob`). Логирование — через `GatewayLog`. Итог успешной обработки одинаков: если заказ в статусе `NEW`/`AWAITING_PAYMENT` → `changeStatus(ACCEPTED)`.

**Только один из трёх колбэков проверяет подпись.**

**Raiffeisen e-commerce (`/raiffeisen-ecom/callback`)** — подпись проверяется: заголовок `X-Api-Signature-SHA256` → `RaiffeisenEcomClient::verifyPaymentSignature`. При неверной подписи джоба логирует `callback_rejected` и выходит. Но проверка выполняется только если заданы `publicId` и `secretKey` (`if ($publicId !== '' && $secretKey !== '' && $signature)`) — при пустом секрете подпись не проверяется, конфигурация должна это гарантировать.

**⚠️ Sberbank (`/sberbank/callback`) — подпись не проверяется.** `ProcessSberbankCallbackJob` находит заказ по `mdOrder`/`orderNumber` из тела и определяет успех по полям `operation`/`status` того же тела (`SberbankAcquiringGateway::processPaymentResponse`: `wasSuccessful = operation in [DEPOSITED, APPROVED] || status in [...] || status === 1`). Никакого HMAC/checksum.

**⚠️ Raiffeisen acquiring (`/raiffeisen/callback`) — подпись тоже не проверяется.** `ProcessRaiffeisenCallbackJob` находит заказ по `number` (из `orderId`) и определяет успех по `status` из тела (`RaiffeisenAcquiringGateway::processPaymentResponse`: `wasSuccessful = status in SUCCESS_STATUSES`). Никакой верификации отправителя.

Практически по обоим: любой, кто знает номер заказа (он в URL и письмах) и endpoint, может отправить, например, `{"orderNumber": "...", "operation": "DEPOSITED", "status": 1}` (Сбербанк) или `{"orderId": "...", "status": "SUCCESS"}` (Райффайзен-эквайринг) и пометить заказ оплаченным. **Это находка уровня «блокер» для приёмки.**

---

## Инфраструктурные и служебные эндпоинты

За пределами `routes/api.php`, но должны быть зафиксированы:

| Эндпоинт | Файл | Замечание |
|---|---|---|
| `GET /api-docs/metrics` | `routes/web.php` | **Без middleware.** Отдаёт тайминги всех маршрутов. Гейт — только флаг `api_metrics.metrics_route_enabled` (по умолчанию `true`). Отдаёт `{"routes":[]}`, пока сбор метрик выключен (например, без Redis/при `API_METRICS_STORE_ENABLED=false`); при включённом сборе публично раскрывает тайминги API. |
| `POST /internal/cache/invalidate` | `apps/frontend/server/index.ts` | SSR-сервер. Секрет проверяется, **но при пустом `FRONTEND_SSR_CACHE_INVALIDATE_SECRET` проверка пропускается** — незаданная переменная открывает эндпоинт всем. |
| `GET /up` | `bootstrap/app.php` | Health-check Laravel. |
| `GET /admin_sv/orders/{id}/print` | `routes/web.php` | Под Filament-аутентификацией. |
| `GET /{any}` | `routes/web.php` | Catch-all, отдаёт `index.html` мимо `api|admin_sv|filament|livewire|storage`. |
| `OPTIONS /api/v1/{any}` | `routes/api.php` | Ручной preflight поверх глобального `HandleCors`. |

---

## Сквозные аспекты (относятся ко всем эндпоинтам)

- **Единый формат ошибок** — в `bootstrap/app.php`: `ValidationException` → 422 `{message, errors}`, `AuthenticationException` → 401, `HttpException` → его код, прочее → 500. CORS-заголовки добавляются даже к ошибкам.
- **Кэш и инвалидация** — hot/cold-сегментация, cache-tags, прогрев `cache:warm-hot`, SSR-кэш на фронте с эндпоинтом сброса.
- **Входящих 1С-эндпоинтов нет.** Синхронизация с 1С только исходящая, по расписанию (`inventory:sync-1c-minute`, выгрузка заказов). Интегратору искать входящие webhook'и не нужно.
- **Безопасность** — rate limiting, CORS и прочие сквозные риски вынесены в раздел «Риски безопасности и план устранения» ниже (Р-4, Р-7).

---

## Риски безопасности и план устранения

Раздел — для приёмки и планирования защиты. Каждый риск: суть, где в коде, влияние на бизнес, **статус проверки** (по коду / воспроизведён на локальном бэке ветки main) и **что сделать**. Приоритеты: 🔴 блокер (нельзя в прод), 🟠 высокий, 🟡 средний, 🟢 гигиена.

### 🔴 Р-1. Платёжные колбэки не проверяют подпись отправителя

- **Где:** `ProcessSberbankCallbackJob`, `SberbankAcquiringGateway::processPaymentResponse`; `ProcessRaiffeisenCallbackJob`, `RaiffeisenAcquiringGateway::processPaymentResponse`.
- **Суть:** два из трёх колбэков (`/payment/sberbank/callback`, `/payment/raiffeisen/callback`) определяют факт оплаты по полям тела запроса (`operation`/`status`) без верификации, что запрос действительно от банка. Заказ ищется по номеру (публичному) и переводится в `ACCEPTED`. Подпись проверяет только `raiffeisen-ecom`.
- **Влияние:** подделка статуса оплаты → отгрузка неоплаченного товара. Прямой финансовый убыток. Самый критичный риск системы.
- **Проверка:** по коду (джобы и gateway'и прочитаны).
- **Устранение:**
  1. Верифицировать подпись/checksum каждого колбэка по алгоритму провайдера (HMAC от тела с секретным ключом) **до** любого изменения статуса; при провале — `callback_rejected` и выход (как уже сделано в `raiffeisen-ecom`).
  2. Сверять сумму из колбэка с суммой заказа.
  3. Ограничить источник по IP-allowlist банка (на уровне nginx/middleware).
  4. Идемпотентность: повторный колбэк по уже оплаченному заказу не должен менять состояние.
  5. Для `raiffeisen-ecom` — сделать проверку подписи **обязательной**: если `secret_key` пуст, колбэк отклонять, а не пропускать (сейчас пустой секрет отключает проверку).

### 🔴 Р-2. Захват аккаунта через оформление заказа

- **Где:** `OrderController::store`, ветка «пользователь существует» (`Auth::login($existingUser)`).
- **Суть:** `POST /orders` доступен гостю. Если указать `contact_email` существующего пользователя, код авторизует текущую сессию под этим аккаунтом **без пароля**.
- **Влияние:** любой гость получает доступ к личному кабинету произвольного пользователя (история заказов, адреса, ПДн) — зная лишь его email.
- **Проверка:** ✅ **воспроизведён на локальном бэке** — гость оформил заказ с email жертвы, затем `GET /auth/me` вернул жертву, а `GET /orders` показал её заказы.
- **Устранение:** при гостевом заказе с занятым email **не логинить** под чужим аккаунтом. Варианты: (а) создавать заказ как гостевой (`user_id = null`, привязка по email без авторизации сессии); (б) если нужен вход — только после подтверждения владения email (magic-link/код). Автологин допустим лишь для **только что автосозданного** пользователя.

### 🟠 Р-3. Клиент задаёт стоимость доставки и сборки

- **Где:** `OrderController::store` — `$finalDeliveryCost = $validated['delivery_cost'] ?? $deliveryCost` (и то же для `assembly_cost`).
- **Суть:** поля `delivery_cost`/`assembly_cost` из тела запроса записываются в заказ как есть, без пересчёта на сервере (для `pickup` доставка форсируется в 0). Комментарий в коде утверждает обратное — поведение ему противоречит.
- **Влияние:** покупатель обнуляет или занижает доставку/сборку → недобор выручки на каждом заказе. `subtotal` защищён (берётся из серверной корзины), а доставка/сборка — нет.
- **Проверка:** ✅ **воспроизведён** — заказ принят с `delivery_cost=777.77`, `assembly_cost=555.55`; `total` вырос ровно на эти суммы, пересчёта не было. Аналогично проходит `delivery_cost=0`.
- **Устранение:** игнорировать клиентские `delivery_cost`/`assembly_cost`; всегда считать доставку server-side через `ShippingCalculationService` по локации/методу/услугам и брать результат калькулятора. Клиентские значения — максимум для предварительного отображения, не для записи в заказ.

### 🟠 Р-4. Отсутствует rate limiting

- **Где:** `bootstrap/app.php`, `routes/api.php` — ни `throttle`, ни `RateLimiter` (проверено grep по `app/`, `routes/`, `bootstrap/`).
- **Суть:** ни один эндпоинт не ограничен по частоте.
- **Влияние:** перебор паролей (`login`), спам-регистрация, рассылка писем сброса (`password/forgot`), спам-заказы и спам-отзывы, нагрузка на очереди/почту, брутфорс платёжных колбэков.
- **Проверка:** по коду.
- **Устранение:** применить `throttle`-middleware адресно:
  - `login`, `password/forgot`, `password/reset` — жёстко (например 5–10/мин на IP+email);
  - `register`, `POST /orders`, `POST /products/{id}/reviews`, `POST /newsletter/subscribe` — умеренно;
  - платёжные колбэки — лимит + IP-allowlist.
  Laravel: именованные лимитеры (`RateLimiter::for(...)`) + `throttle:name` на группах маршрутов.

### 🟡 Р-5. Публичный эндпоинт метрик

- **Где:** `routes/web.php` → `GET /api-docs/metrics` (`ApiMetricsController`), без middleware.
- **Суть:** при включённом сборе (`api_metrics.metrics_route_enabled`, по умолчанию `true`) отдаёт тайминги всех маршрутов кому угодно.
- **Влияние:** утечка внутренней телеметрии (структура API, узкие места) — помощь атакующему в разведке.
- **Устранение:** закрыть аутентификацией (Filament/админ-middleware) или отключить маршрут в проде; не полагаться на «сбор выключен».

### 🟡 Р-6. Открытый эндпоинт сброса SSR-кэша

- **Где:** `apps/frontend/server/index.ts` → `POST /internal/cache/invalidate`.
- **Суть:** секрет проверяется, **но при пустом `FRONTEND_SSR_CACHE_INVALIDATE_SECRET` проверка пропускается целиком** — незаданная переменная открывает эндпоинт всем.
- **Влияние:** сброс SSR-кэша по запросу извне → рост нагрузки, деградация витрины (cache-busting как DoS-вектор).
- **Устранение:** при пустом секрете — **отказывать** (fail-closed), а не пропускать. Секрет сделать обязательным в проде; ограничить источник (внутренняя сеть/IP).

### 🟡 Р-7. Разрозненная конфигурация CORS

- **Где:** три места — `config/cors.php` (`HandleCors`), ручной preflight в `routes/api.php`, обработчик в `bootstrap/app.php`→`withExceptions`.
- **Суть:** логика дублируется и расходится; при пустом списке origins есть фолбэк на `*` вместе с `Access-Control-Allow-Credentials: true`.
- **Влияние:** при ошибке конфигурации — разрешение запросов с произвольных origin с передачей кук (риск для сессионной авторизации).
- **Устранение:** свести CORS к единственному источнику (`config/cors.php`), убрать ручные preflight-блоки, задать явный whitelist origin, исключить `*` при `supports_credentials: true`.

### 🟢 Проверено — защита на месте (не риск)

- **IDOR на `addresses`/`orders`** — проверка владения реализована (`user_id`, для гостевых заказов — совпадение `contact_email`), чужие объекты по прямому `id` возвращают `403`. Воспроизведено: sanctum-эндпоинты без токена дают `401`. Оставить как есть.
- **`subtotal` заказа** — берётся из серверной корзины, подмене не поддаётся (в отличие от `delivery_cost`, см. Р-3).

### Итоговая таблица приоритетов

| # | Приоритет | Риск | Проверка | Где |
|---|---|---|---|---|
| Р-1 | 🔴 Блокер | Колбэки Сбербанк/Райфф-эквайринг без подписи → подделка оплаты | по коду | `Process*CallbackJob`, gateway'и |
| Р-2 | 🔴 Блокер | Захват аккаунта через `POST /orders` (email без пароля) | ✅ воспроизведён | `OrderController::store` |
| Р-3 | 🟠 Высокий | Подмена `delivery_cost`/`assembly_cost` клиентом | ✅ воспроизведён | `OrderController::store` |
| Р-4 | 🟠 Высокий | Нет rate limiting во всём API | по коду | `bootstrap/app.php`, `routes/api.php` |
| Р-5 | 🟡 Средний | `/api-docs/metrics` публичен | по коду | `routes/web.php` |
| Р-6 | 🟡 Средний | SSR `/internal/cache/invalidate` при пустом секрете | по коду | `apps/frontend/server/index.ts` |
| Р-7 | 🟡 Средний | CORS в трёх местах, фолбэк на `*` | по коду | `cors.php`, `routes/api.php`, `bootstrap/app.php` |

**Порядок работ:** сначала Р-1 и Р-2 (блокеры, деньги и доступ), затем Р-3/Р-4, потом Р-5–Р-7. Р-1 и Р-6 закрываются малыми правками (проверка подписи / fail-closed), Р-2 и Р-3 требуют изменения логики оформления заказа.

> **Прочее (не безопасность, вынесено для полноты):** параметр `published` в `GET /articles` — рудимент (no-op, см. описание в OpenAPI); `RussianRegionsSeeder` падает на отсутствующей таблице `federal_districts` (регионы API работают через `ShippingLocation`); OpenAPI-спека покрывает 100% маршрутов (81 операция, 35 схем), выверена по коду ветки main и проверена живыми вызовами.
