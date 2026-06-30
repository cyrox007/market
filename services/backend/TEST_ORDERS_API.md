# Тестовые запросы для API заказов

## Важно!

API заказов использует **POST** метод, а не GET.

Корзина и гостевое оформление работают через middleware **`api-session`** (сессия + cookies), поэтому для сценария:
**добавить в корзину → создать заказ** нужно сохранять cookies между запросами.

## 1. Создание заказа с доставкой (delivery)

```bash
# 0) (опционально) очистить корзину и зафиксировать cookie-сессию
curl -c cookies.txt -b cookies.txt -X DELETE "http://localhost:8000/api/v1/cart"

# 1) добавить товары в корзину (пример)
curl -c cookies.txt -b cookies.txt -X POST "http://localhost:8000/api/v1/cart" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "quantity": 2
  }'

# 2) затем создать заказ
curl -c cookies.txt -b cookies.txt -X POST "http://localhost:8000/api/v1/orders" \
  -H "Content-Type: application/json" \
  -d '{
    "contact_name": "Иван Иванов",
    "contact_phone": "79991234567",
    "contact_email": "test@example.com",
    "payment_method": "card",
    "delivery_type": "delivery",
    "shipping_location_id": 1,
    "shipping_method_id": 1,
    "delivery_handling_type_id": 2,
    "delivery_floor": 4,
    "requires_assembly": true,
    "address": {
      "city": "Москва",
      "street": "Ленина",
      "house": "1",
      "apartment": "10"
    }
  }'
```

**Готовая строка для браузера/Postman (POST с JSON):**

```
POST http://localhost:8000/api/v1/orders
Content-Type: application/json

{
  "contact_name": "Иван Иванов",
  "contact_phone": "79991234567",
  "contact_email": "test@example.com",
  "payment_method": "card",
  "delivery_type": "delivery",
  "shipping_location_id": 1,
  "shipping_method_id": 1,
  "delivery_handling_type_id": 2,
  "delivery_floor": 4,
  "requires_assembly": true,
  "address": {
    "city": "Москва",
    "street": "Ленина",
    "house": "1",
    "apartment": "10"
  }
}
```

## 2. Создание заказа с самовывозом (pickup)

```bash
curl -c cookies.txt -b cookies.txt -X POST "http://localhost:8000/api/v1/orders" \
  -H "Content-Type: application/json" \
  -d '{
    "contact_name": "Петр Петров",
    "contact_phone": "79991234567",
    "contact_email": "test@example.com",
    "payment_method": "cash",
    "delivery_type": "pickup"
  }'
```

**Готовая строка для браузера/Postman:**

```
POST http://localhost:8000/api/v1/orders
Content-Type: application/json

{
  "contact_name": "Петр Петров",
  "contact_phone": "79991234567",
  "contact_email": "test@example.com",
  "payment_method": "cash",
  "delivery_type": "pickup"
}
```

## 3. Создание заказа с указанием shipping_method_id

```bash
curl -c cookies.txt -b cookies.txt -X POST "http://localhost:8000/api/v1/orders" \
  -H "Content-Type: application/json" \
  -d '{
    "contact_name": "Сергей Сергеев",
    "contact_phone": "79991234567",
    "contact_email": "test@example.com",
    "payment_method": "card",
    "delivery_type": "delivery",
    "shipping_location_id": 1,
    "shipping_method_id": 1,
    "delivery_handling_type_id": 2,
    "delivery_floor": 5,
    "address": {
      "city": "Санкт-Петербург",
      "street": "Невский проспект",
      "house": "10",
      "apartment": "20"
    }
  }'
```

## Параметры запроса

### Обязательные для всех заказов:

-   `contact_name` - Имя покупателя
-   `contact_phone` - Телефон покупателя
-   `contact_email` - Email покупателя
-   `payment_method` - Способ оплаты (card, cash, installment)
-   `delivery_type` - Тип доставки (delivery, pickup)

### Обязательные для delivery:

-   `shipping_location_id` - ID локации доставки

### Опциональные для delivery:

-   `shipping_method_id` - ID метода доставки (Vanilo). Если указан — должен существовать (иначе 422).
-   `delivery_handling_type_id` - ID типа обработки доставки
-   `delivery_floor` - Этаж доставки (обязателен если тип обработки требует этаж)
-   `requires_assembly` - Требуется ли сборка (boolean)
-   `address` - Объект с адресом (если не используется address_id)
-   `address_id` - ID существующего адреса пользователя
-   `delivery_date` - Дата доставки
-   `delivery_time` - Время доставки
-   `comment` - Комментарий к заказу

## Примечания

1. Перед созданием заказа необходимо добавить товары в корзину через `POST /api/v1/cart` (и сохранить cookies между запросами).
2. `delivery_cost` рассчитывается как **доставка + обработка доставки (подъём/разгрузка)**.
3. `assembly_cost` рассчитывается **только если `requires_assembly=true`** (и хранится отдельно).
4. При указании `shipping_method_id` используется расчет через `CarrierService` (Vanilo shipping method).
5. Без `shipping_method_id` используется базовый расчет через `ShippingCalculationService` по настройкам локации.
