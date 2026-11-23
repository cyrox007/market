# Прямой API чтения заказов для 1С — контракт v1

**Статус:** новая функция в ветке `feat/onec-direct-orders-read-20261123`; не развёрнута. **Источник данных:** первичная БД Laravel (магазин), без промежуточного API. Ручка **только читает**: не изменяет заказы, статусы, оплаты, резервы и очереди.

## 1. Подключение и права

| Параметр | Значение |
| --- | --- |
| Метод и URL | `GET /api/v1/integration/1c/orders` |
| Заголовок авторизации | `X-1C-Orders-Token: <отдельный секрет>` |
| Формат | JSON UTF-8, имена полей в `snake_case` |
| По умолчанию | **Выключено** |
| HTTPS | Обязательно на production; незашифрованное соединение отклоняется |
| Доступ | Только чтение заказов; нет авторизации пользователя/администратора |
| Лимит | 30 запросов в минуту на IP по умолчанию; настройка 1..120 |
| Размер ответа | Не больше 100 заказов на страницу; по умолчанию 50 |
| Кэш | `Cache-Control: private, no-store, max-age=0` |

Конфигурация Laravel в секретном `.env`:

- `ONEC_ORDERS_READ_ENABLED=true` — включить **только после** подготовки HTTPS.
- `ONEC_ORDERS_READ_TOKEN=...` — случайный секрет длиной не менее 32 символов, отличный от исходящего `ONEC_API_KEY`.
- `ONEC_ORDERS_READ_PER_MINUTE=30` — отдельный лимит.
- После изменения переменных окружения обновить кэш конфигурации Laravel.

**Нельзя** передавать секрет в query string, переписке, логах или использовать пароль администратора. При размещении за обратным прокси проверить корректность определения HTTPS. Ограничение по IP и ротация секретов требуют настройки инфраструктуры.

```bash
curl --fail-with-body -H 'Accept: application/json' \
  -H 'X-1C-Orders-Token: <секрет от администратора>' \
  'https://new.svetofor-mebel.ru/api/v1/integration/1c/orders?limit=50'
```

Адрес в примере — ориентир; при развертывании следует использовать реальный домен/API endpoint магазина.

## 2. Кто попадает в список — период охлаждения

**Все фильтры применяются после обязательного серверного барьера.** Запрос по `order_id` не может показать скрытый заказ.

Для текущего статуса `accepted`, `assembling`, `assembled`, `shipped`, `in_transit`, `delivered` одновременно должны выполняться два условия:

1. `cooling_until` заполнен — у заказа известен дедлайн 60-минутного периода охлаждения.
2. `cooling_until <= now()` — дедлайн наступил.

`new`, `manager_processing`, `awaiting_payment` не передаются независимо от прошедшего времени. Старые заказы с `cooling_until=null` **не экспортируются автоматически**.

`cancelled` выдаётся **только если заказ ранее прошёл допуск**, что подтверждено либо `integration_sent_at >= cooling_until`, либо записью истории о допущенном статусе (после дедлайна). Отмена в пределах 60 минут остаётся скрытой и после истечения исходного дедлайна.

`cooling_until` — время окончания периода, **не** гарантированное время реальной готовности к исполнению. Если фоновый процесс перехода задержался, заказ остаётся скрытым до разрешённого статуса.

## 3. Фильтры и их сочетание

| Параметр | Тип и пример | Смысл |
| --- | --- | --- |
| `order_id` | Положительное целое, `12345` | Точный ID заказа в Laravel |
| `number` | Строка до 100 символов | Точный номер заказа, видимый покупателю |
| `status` | `assembling,assembled` | Один или несколько кодов статусов, разделённых запятой; внутри работает ИЛИ |
| `created_from` | `2026-11-01` | Создан не раньше начала календарной даты UTC |
| `created_to` | `2026-11-23` | Создан не позднее окончания календарной даты UTC |
| `cooling_from` | `2026-11-01` | Дедлайн охлаждения не раньше начала даты UTC |
| `cooling_to` | `2026-11-23` | Дедлайн охлаждения не позднее окончания даты UTC |
| `delivery_type` | `delivery` / `pickup` | Доставка курьером к покупателю / самовывоз из выбранного склада или связанной с ним точки выдачи |
| `payment_method` | Например, `cash` | **Код** способа оплаты, не его русское название |
| `limit` | 1..100; по умолчанию 50 | Максимальное количество заказов в странице |
| `after_id` | Целое >= 0 | Следующая страница: только ID строго больше значения |
| `snapshot_max_id` | Целое >= 0 | Верхняя граница ID, полученная на первой странице |

Между разными фильтрами действует логическое **И**. Даты задаются в `YYYY-MM-DD`, границы включительные. Временные отметки в JSON — **ISO 8601 UTC** с `Z`. Неизвестный статус или неверные границы даты → `422`.

```http
GET /api/v1/integration/1c/orders?status=assembling,assembled&limit=50
GET /api/v1/integration/1c/orders?delivery_type=pickup&created_from=2026-11-01
GET /api/v1/integration/1c/orders?cooling_from=2026-11-20&cooling_to=2026-11-23
GET /api/v1/integration/1c/orders?order_id=12345
GET /api/v1/integration/1c/orders?payment_method=cash&status=assembling
```

### Пагинация и её ограничения

Ответ отсортирован по `orders.id ASC`. На первой странице сохраните `meta.snapshot_max_id`. Если `has_more=true`, передайте те же фильтры, `after_id=meta.next_after_id` и прежний `snapshot_max_id`. Продолжайте до `has_more=false`.

**Это не курсор событий.** Ограничение максимального ID не создаёт транзакционный снимок: если старый заказ изменится или станет доступным между страницами, он может быть пропущен либо показан уже в новом состоянии. `orders.updated_at` также меняется из-за служебных операций и не является надёжной версией бизнес-данных. Для постоянной синхронизации изменений потребуется отдельный журнал с версиями и подтверждением 1С. Эта ручка предназначена для фильтруемого списка, первичной выборки и сверки.

## 4. Пример ответа

Данные ниже вымышленные, показывают структуру, а не реальные заказы.

```json
{
  "data": [{
    "id": 12345,
    "number": "2026-00123",
    "status": "assembling",
    "status_label": "Собирается",
    "created_at": "2026-11-23T09:00:00.000000Z",
    "updated_at": "2026-11-23T10:10:00.000000Z",
    "cooling_until": "2026-11-23T10:00:00.000000Z",
    "customer": {"name": "Иван Иванов", "phone": "+79001234567", "email": "buyer@example.test"},
    "payment": {"method_code": "cash", "method_name": "Наличными", "is_paid": false},
    "amounts": {
      "currency": "RUB", "subtotal": "30000.00", "delivery_total": "500.00",
      "handling_in_delivery": "200.00", "assembly": "0.00",
      "additional_services": "0.00", "total": "30500.00"
    },
    "delivery": {
      "type": "delivery", "date": null, "time": null, "days_min": 2, "days_max": 5,
      "floor": 3, "requires_assembly": false,
      "handling": {"code": "manual_lift", "name": "Подъём вручную"},
      "address": {
        "text": "Липецк, ул. Примерная, д. 10",
        "directory_external_id": "building-001", "gar_guid": null,
        "kladr_code": "4800000012345", "building_kladr_code": "4800000012345",
        "kladr_level": "building", "details": {"apartment": "14"}
      },
      "pickup": null
    },
    "fulfillment": {
      "multi_warehouse": false, "warehouse_external_id": "WH-001",
      "warehouse_allocations": [{
        "warehouse_id": 7, "warehouse_external_id": "WH-001",
        "items": [{"product_id": 91, "quantity": 2, "preorder_quantity": 0}]
      }]
    },
    "bundle_snapshot": null,
    "additional_services": [],
    "items": [{
      "line_id": 501, "product_id": 91, "product_external_id": "1c-product-001",
      "sku": "SHK-001", "name": "Шкаф, вариант на момент покупки", "quantity": 2,
      "unit_price": "15000.00", "line_total": "30000.00",
      "display_source": "snapshot", "colors": [], "materials": [],
      "configuration": [], "bundle": null, "bundle_pricing": null
    }],
    "status_history": [
      {"status": "assembling", "occurred_at": "2026-11-23T10:00:10.000000Z"},
      {"status": "manager_processing", "occurred_at": "2026-11-23T09:00:00.000000Z"}
    ]
  }],
  "meta": {
    "limit": 50, "snapshot_max_id": 12345, "next_after_id": null,
    "has_more": false, "as_of": "2026-11-23T10:12:00.000000Z", "mode": "list"
  }
}
```

### 4.1. Самовывоз — отдельная форма ответа

Если `delivery.type = "pickup"`, **адрес покупателя не используется**: `delivery.address = null`. Адрес, по которому выдаётся заказ, находится в **`delivery.pickup.address`**. Это исторический адрес физического объекта склада (или связанной с ним точки выдачи), выбранного при оформлении заказа. Не путайте его со складом, который может числиться источником товара при курьерской доставке.

Укороченный пример записи самовывоза (все остальные поля заказа, включая `items`, `amounts` и `status_history`, такие же, как в общем формате):

```json
{
  "id": 12346,
  "number": "2026-00124",
  "status": "assembling",
  "delivery": {
    "type": "pickup",
    "date": null,
    "time": null,
    "days_min": 0,
    "days_max": 2,
    "floor": null,
    "requires_assembly": false,
    "handling": null,
    "address": null,
    "pickup": {
      "warehouse_id": 7,
      "warehouse_external_id": "WH-LIP-01",
      "store_id": 12,
      "point_name": "Пункт самовывоза на Складской",
      "address": {
        "text": "Липецк, улица Складская, 10",
        "directory_external_id": "building-001",
        "gar_guid": null,
        "kladr_code": "4800000012345",
        "building_kladr_code": "4800000012345",
        "kladr_level": "building",
        "kladr_hierarchy": [],
        "details": []
      },
      "hours": "Пн–Пт 09:00–18:00",
      "opening_hours": null,
      "timezone": "Europe/Moscow"
    }
  },
  "fulfillment": {
    "multi_warehouse": false,
    "warehouse_external_id": "WH-LIP-01",
    "warehouse_allocations": [{
      "warehouse_id": 7,
      "warehouse_external_id": "WH-LIP-01",
      "items": [{"product_id": 91, "quantity": 2, "preorder_quantity": 0}]
    }]
  }
}
```

Если покупатель выбирает выдачу **на самом складе**, без отдельного магазина, `delivery.pickup.store_id = null`, `point_name` берётся из названия склада. Когда выбрана связанная точка выдачи, `store_id` содержит внутренний ID магазина сайта, а `point_name` — историческое название точки. Адрес в обоих случаях берётся из выбранного физического объекта/склада, а не из `stores.address`, и фиксируется в `fulfillment_snapshot`.

Если старый заказ не содержит сохранённого адреса точки, `delivery.pickup.address = null`. Это **пробел в исторических данных**, требующий проверки, а не разрешение автоматически подставить сегодняшний адрес склада.

## 5. Словарь всех полей

### 5.1. Корень, курсор и заказ

| Поле | Тип | Значение |
| --- | --- | --- |
| `data` | array | Только доступные и подходящие под фильтры заказы |
| `meta.limit` | integer | Размер страницы |
| `meta.snapshot_max_id` | integer | Верхняя граница ID этой первоначальной выборки |
| `meta.next_after_id` | integer/null | Продолжение, если `has_more` |
| `meta.has_more` | boolean | Существуют ли следующие подходящие записи |
| `meta.as_of` | ISO 8601 UTC | Время формирования ответа; не транзакционный snapshot |
| `meta.mode` | string | Всегда `list`, журнал изменений пока не реализован |
| `id` | integer | ID заказа **магазина**, не ID API-посредника/1С |
| `number` | string | Человекочитаемый номер заказа |
| `status` | string | Машинный код статуса (таблица ниже) |
| `status_label` | string | Русская подпись к статусу |
| `created_at` | ISO 8601 UTC/null | Время создания |
| `updated_at` | ISO 8601 UTC/null | Включает служебные изменения, не бизнес-версия |
| `cooling_until` | ISO 8601 UTC/null | Дедлайн охлаждения |

### 5.2. Покупатель, оплата, суммы

| Поле | Тип | Значение |
| --- | --- | --- |
| `customer.name` | string/null | Имя контакта из заказа |
| `customer.phone` | string/null | Сохранённый номер телефона |
| `customer.email` | string/null | Email на момент оформления |
| `payment.method_code` | string/null | Код способа оплаты из магазина |
| `payment.method_name` | string/null | Подпись из текущего справочника методов, не исторический реквизит |
| `payment.is_paid` | boolean | Есть подтверждённый полностью оплаченный платёж на сумму заказа; `false` также для частичных платежей/оплаты при получении |
| `amounts.currency` | string | `RUB` |
| `amounts.subtotal` | decimal string | Стоимость товаров из заказа |
| `amounts.delivery_total` | decimal string | Общая стоимость доставки, **может включать подъём** |
| `amounts.handling_in_delivery` | decimal string | Часть доставки, соответствующая подъёму/обработке |
| `amounts.assembly` | decimal string | Цена сборки |
| `amounts.additional_services` | decimal string | Сумма допуслуг из складского снимка |
| `amounts.total` | decimal string | Сохранённая итоговая сумма заказа |

**Важно:** `handling_in_delivery` уже учитывается внутри `delivery_total`. Не складывать все денежные поля механически. Основной результат расчёта — `total`. Цена сохраняется в строке заказа; не пересчитывать её по сегодняшнему каталогу.

### 5.3. Доставка, адрес и распределения

| Поле | Тип | Значение |
| --- | --- | --- |
| `delivery.type` | `delivery`/`pickup` | Доставка или самовывоз |
| `delivery.date` | YYYY-MM-DD/null | Назначенная дата, если сохранена; не путать с оценкой |
| `delivery.time` | string/null | Назначенный интервал, если сохранён |
| `delivery.days_min`/`days_max` | integer/null | Для доставки — ориентировочный срок транспортировки; для самовывоза — ориентировочный срок подготовки к выдаче, не обещанная дата |
| `delivery.floor` | integer/null | Этаж |
| `delivery.requires_assembly` | boolean | Нужна сборка |
| `delivery.handling` | object/null | Тип обработки: `code`, `name` |
| `delivery.address` | object/null | **Только доставка:** исторический адрес покупателя; при самовывозе всегда `null` |
| `delivery.pickup` | object/null | **Только самовывоз:** сведения о выбранной выдаче; при доставке всегда `null` |
| `delivery.pickup.warehouse_id` | integer/null | Внутренний идентификатор склада сайта, из которого производится выдача |
| `delivery.pickup.warehouse_external_id` | string/null | Внешний ID выбранного склада для сопоставления в 1С |
| `delivery.pickup.store_id` | integer/null | ID связанного магазина/точки выдачи сайта; `null` при выдаче непосредственно на складе |
| `delivery.pickup.point_name` | string/null | Историческое название выбранной точки или склада |
| `delivery.pickup.address` | object/null | **Адрес самовывоза (склада/физического объекта)**, сохранённый при оформлении. Поля те же, что у `delivery.address` |
| `delivery.pickup.address.text` | string/null | Текст адреса выдачи: из сохранённого `fulfillment_snapshot.address` или исторической резервной копии адреса заказа; **не из текущей карточки склада** |
| `delivery.pickup.address.directory_external_id` | string/null | Исторический ID адресного объекта выбранного склада |
| `delivery.pickup.address.gar_guid` / `kladr_code` | string/null | Исторические ГАР/КЛАДР адреса склада, если они были сохранены |
| `delivery.pickup.address.building_kladr_code` / `kladr_level` / `kladr_hierarchy` | string/null или array | КЛАДР здания, уровень и иерархия по сохранённому адресу выдачи |
| `delivery.pickup.address.details` | object/null | Дополнительные сведения адресного снимка точки выдачи, если они были |
| `delivery.pickup.hours` | string/null | Сохранённая текстовая подпись часов работы точки |
| `delivery.pickup.opening_hours` | object/array/null | Сохранённое расписание точки, если выбрана связанная торговая точка |
| `delivery.pickup.timezone` | string/null | Часовой пояс точки, записанный при выборе варианта самовывоза |
| `delivery.address.text` | string/null | Исторический адрес **покупателя для доставки**, не редактируемая адресная книга |
| `delivery.address.directory_external_id` | string/null | Внешний ID здания классификатора |
| `delivery.address.gar_guid` | string/null | GUID ГАР, если есть |
| `delivery.address.kladr_code` | string/null | Самый точный известный код КЛАДР |
| `delivery.address.building_kladr_code` | string/null | КЛАДР именно здания (если известен) |
| `delivery.address.kladr_level` | string/null | Уровень объекта, для которого известен КЛАДР |
| `delivery.address.kladr_hierarchy[]` | array | Сохранённый путь КЛАДР: `kind` — тип уровня и `code` — код объекта; пустой, если в историческом снимке нет кодов |
| `delivery.address.details` | object/null | Зафиксированные квартира/комната/подъезд/этаж/домофон/комментарий |
| `fulfillment.multi_warehouse` | boolean | Заказ размещён на нескольких складах |
| `fulfillment.warehouse_external_id` | string/null | Общий внешний ID склада, **null** при многоскладском заказе |
| `fulfillment.warehouse_allocations` | array | Исторические распределения по складам |
| `warehouse_allocations[].warehouse_id` | integer/null | Внутренний ID склада **сайта**, не код 1С |
| `warehouse_allocations[].warehouse_external_id` | string/null | Внешний ID склада |
| `warehouse_allocations[].items[].product_id` | integer/null | Внутренний ID SKU сайта |
| `warehouse_allocations[].items[].quantity` | integer | Количество SKU с этого склада |
| `warehouse_allocations[].items[].preorder_quantity` | integer | Из него — количество под заказ |

**Самовывоз:** в `delivery.pickup` всегда различаются **склад-источник** (`warehouse_id`, `warehouse_external_id`) и **магазин/точка выдачи** (`store_id`, если выбрана). Для магазинов в этой схеме нет отдельного гарантированного `store_external_id` 1С. **Нельзя** использовать внешний ID склада вместо ID магазина. Адрес выдачи — `delivery.pickup.address`, из сохранённого `fulfillment_snapshot`; актуальный склад или магазин из БД для восстановления старых адресов не опрашивается. Если исторический адрес отсутствует — `null` и ручная сверка. Если нет распределений в старом снимке, склад тоже не выдумывается.

### 5.4. Строки товаров, вариации, комплекты, услуги

| Поле | Тип | Значение |
| --- | --- | --- |
| `items[].line_id` | integer | ID конкретной строки заказа |
| `items[].product_id` | integer | **Внутренний** ID SKU, не идентификатор товара 1С |
| `items[].product_external_id` | string/null | Внешний ID SKU, зафиксированный в `display_snapshot.article` |
| `items[].sku` | string/null | Сохранённый SKU/артикул, если присутствует |
| `items[].parent_name` | string/null | Историческое название родительского товара для вариации, если сохранено |
| `items[].name` | string | Историческое имя из `order_items.name` |
| `items[].quantity` | integer | Количество |
| `items[].unit_price` | decimal string | Цена единицы из сохранённой строки |
| `items[].line_total` | decimal string | Сохранённая сумма строки |
| `items[].display_source` | `snapshot`/`legacy` | Есть ли подробный исторический снимок отображения |
| `items[].colors` | array | Сохранённые цветовые решения (структура объектов текущего снимка) |
| `items[].materials` | array | Сохранённые данные материалов |
| `items[].configuration` | array | Сохранённая вариативная конфигурация |
| `items[].specifications` | array | Полные исторические характеристики SKU (включая размеры/вес при наличии), каждая строка: `name`, `slug`, `value` |
| `items[].bundle` | array/object/null | Сохранённая связь позиции с комплектом |
| `items[].bundle_pricing` | object/null | Сохранённая детализация цены комплекта |
| `bundle_snapshot` | array/object/null | Снимок комплекта заказа, если был сформирован |
| `additional_services[]` | array | Дополнительные услуги заказа |
| `additional_services[].code` | string/null | Код услуги из справочника |
| `additional_services[].name` | string | Зафиксированное название, при отсутствии — справочник |
| `additional_services[].price` | decimal string | Сохранённая цена услуги |
| `additional_services[].price_type` | string/null | Тип цены услуги |
| `status_history[]` | array | История изменений статуса |
| `status_history[].status` | string | Код статуса на момент события |
| `status_history[].occurred_at` | ISO 8601 UTC/null | Когда произошёл переход |

**Цветовые решения в `items[].colors`:** каждая строка содержит `element` (или `null` для основного цвета) с `id, name, slug`, а также `solution` с `id, name, slug, icon_url` и массивом `base_colors`. Базовый цвет включает `id, name, slug, hex`. `icon_url` — ссылка на текущий медиафайл, а не гарантированно неизменяемое историческое изображение. Строки `materials`, `configuration`, `specifications` используют `name` (название характеристики), `slug` (стабильный код), `value` (зафиксированное текстовое значение). Значение `legacy` означает отсутствие части этих сведений.

**Без подмены исторических данных:** если `display_source=legacy`, старые цвета и внешний ID могут отсутствовать. Не подставлять текущие цвета каталога и **не заменять `product_external_id` внутренним `product_id`**. Строка всё равно возвращается, чтобы 1С могла обозначить её как требующую сопоставления.

Комплект может содержать как товар-набор, так и компоненты. Прежде чем проводить документ, согласовать с разработчиком 1С схему учёта, чтобы не задвоить сумму и количество.

История статусов передаётся уже допущенного заказа; её прошлые события могут относиться к периоду охлаждения. Внутренние комментарии сотрудников и их идентификаторы не выдаются.

## 6. Полный список поддерживаемых статусов

| Код | Название | Что означает | Видимость |
| --- | --- | --- | --- |
| `new` | Новый | Создан, не выпущен в исполнение | Нет |
| `manager_processing` | В обработке у менеджера | Период ожидания/охлаждения | Нет |
| `awaiting_payment` | Ожидание оплаты | Ожидает действия по оплате | Нет |
| `accepted` | Принят | Принят к исполнению | После охлаждения |
| `assembling` | Собирается | Подготовка/сборка | После охлаждения |
| `assembled` | Собран | Подготовка завершена | После охлаждения |
| `shipped` | Отправлен | Передан в доставку | После охлаждения |
| `in_transit` | В пути | В процессе доставки | После охлаждения |
| `delivered` | Доставлен | Выполнен | После охлаждения |
| `cancelled` | Отменён | Отмена заказа | Только ранее допущенный |

Таблица соответствует PHP enum `App\Models\Order\OrderStatus`. Отдельный `ready_for_pickup` встречается в конфигурации клиентского отображения, но **не представлен в актуальном enum**; API не считает его поддерживаемым текущим статусом. Оплата — отдельный признак, не статус исполнения.

## 7. Ошибки и действия 1С

| HTTP | Причина | Что делать |
| --- | --- | --- |
| 200 | Запрос выполнен, в том числе при пустом `data` | Обработать `data` и `meta` |
| 401 | Токен отсутствует/неверен, слишком короткий секрет либо API выключен | Остановить повторы, проверить настройки |
| 403 | На production используется HTTP вместо HTTPS | Исправить TLS и настройку reverse proxy |
| 422 | Неверный фильтр, период, статус, курсор или размер страницы | Исправить запрос; ошибки перечислены в `errors` |
| 429 | Превышен лимит | Дождаться `Retry-After` в секундах и повторить с задержкой |
| 500/503 | Временная ошибка приложения/БД | Ограниченный retry, не сбрасывать свой прогресс |

## 8. Что НЕ входит в первый этап

- Нет команды изменения статуса из 1С (вторая ручка — отдельная задача).
- Нет гарантированного инкрементального журнала новых/изменённых заказов и подтверждения факта обработки в 1С.
- Не отключаются старые исходящие задачи `SyncOrderTo1CJob`, не чистятся очереди и не меняются складские резервы.
- Старые записи без `cooling_until` не мигрируются в выгрузку автоматически.
- Полная проверка реальной конфигурации обменного модуля 1С и поля точки самовывоза ещё необходима.

**Рекомендация:** сперва проверить новый GET на тестовом окружении и нескольких контрольных заказах (SKU, комплект, предзаказ, несколько складов, отмена после охлаждения). При переходе на production не дать старому посреднику и новому прямому API одновременно создавать документы в 1С. Разделение чтения и подтверждённой доставки — принципиально.

## 9. Реализация и тесты

- Маршрут: `services/backend/routes/api.php`.
- Контроллер: `services/backend/app/Http/Controllers/Api/OneCOrdersReadController.php`.
- Доступ: `services/backend/app/Http/Middleware/AuthorizeOneCOrdersRead.php`.
- Ресурс: `services/backend/app/Http/Resources/OneCOrderReadResource.php`.
- Конфигурация: `services/backend/config/onec_orders_read.php`.
- Тесты: `services/backend/tests/Feature/Api/OneCOrdersReadTest.php`.

Тестовая команда: `cd services/backend && php artisan test --filter=OneCOrdersReadTest`.
