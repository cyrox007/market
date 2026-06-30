# Система управления остатками и интеграция с 1С

## Обзор

Система управления остатками полностью интегрирована с Vanilo Framework и готова к обмену данными с внешними системами (1С, другие ERP).

## Актуальные инварианты API (каталог/корзина)

- Входной региональный контекст: сначала `shipping_location_id`, затем legacy `region_id`.
- Для списков каталога (`/products`, `/products/search`) региональная видимость применяется на уровне SQL-фильтрации, а не post-filter на фронтенде.
- Для карточек/листингов остатки резолвятся через `WarehouseStockResolver` и кэшируются in-request (`product_id:location_id`) для снижения повторных расчетов.
- Корзина при add/update всегда пересчитывает доступное количество через `StockAvailabilityService` и возвращает `was_adjusted`, если количество автоматически скорректировано.

## Архитектура

### Компоненты системы

1. **StockService** (`app/Services/Inventory/StockService.php`)

    - Основной сервис управления остатками
    - Атомарные операции с остатками
    - Синхронизация с внешними системами

2. **Actions** (`app/Actions/Inventory/`)

    - `DecreaseStockAction` - уменьшение остатков
    - `IncreaseStockAction` - увеличение остатков
    - `ValidateStockAction` - проверка остатков

3. **Интеграции** (`app/Services/Inventory/Integrations/`)

    - `OneCApiService` - интеграция с 1С
    - Реализует `InventorySyncInterface`

4. **Слушатели Vanilo событий** (`app/Listeners/Inventory/`)
    - `HandleOrderCreated` - обработка создания заказа
    - `HandleOrderCancelled` - обработка отмены заказа
    - `HandleOrderCompleted` - обработка завершения заказа

## Интеграция с Vanilo

Система использует стандартные события Vanilo:

-   **`OrderWasCreated`** - автоматически уменьшает остатки
-   **`OrderWasCancelled`** - автоматически возвращает остатки
-   **`OrderWasCompleted`** - обновляет статистику продаж

### Как это работает

1. При создании заказа в `OrderController`:

    ```php
    // Проверка остатков
    $stockValidation = $this->validateStockAction->execute();

    // Создание заказа
    $order = Order::create([...]);

    // Вызов Vanilo события (автоматически уменьшит остатки)
    event(new OrderWasCreated($order));
    ```

2. Слушатель `HandleOrderCreated` автоматически:

    - Уменьшает остатки для всех товаров в заказе
    - Синхронизирует заказ с 1С (если включено)

3. При отмене заказа:

    ```php
    $order->changeStatus(OrderStatus::CANCELLED);
    event(new OrderWasCancelled($order));
    ```

4. Слушатель `HandleOrderCancelled` автоматически:
    - Возвращает остатки для всех товаров
    - Синхронизирует отмену с 1С

## Интеграция с 1С

### Настройка

Добавьте в `.env`:

```env
ONEC_API_ENABLED=true
ONEC_API_BASE_URL=https://your-1c-server.com/api
ONEC_API_KEY=your-api-key
ONEC_API_TIMEOUT=30
```

### API Endpoints 1С

Система ожидает следующие endpoints в 1С:

1. **POST `/api/orders`** - создание заказа

    - Получает JSON с данными заказа
    - Возвращает статус создания

2. **PATCH `/api/orders/{number}/cancel`** - отмена заказа

    - Получает номер заказа
    - Возвращает статус отмены

3. **PATCH `/api/orders/{number}/complete`** - завершение заказа

    - Получает номер заказа
    - Возвращает статус завершения

4. **POST `/api/products/stock`** - получение остатков

    - Получает массив `product_ids`
    - Возвращает массив остатков

5. **GET `/api/products/{sku}`** - получение товара по артикулу
    - Возвращает данные товара

### Формат данных заказа для 1С

```json
{
    "number": "ORD20251211000001",
    "status": "new",
    "user_id": 1,
    "contact_name": "Иван Иванов",
    "contact_phone": "+79991234567",
    "contact_email": "ivan@example.com",
    "subtotal": 10000.0,
    "total": 12000.0,
    "delivery_cost": 1000.0,
    "assembly_cost": 1000.0,
    "payment_method": "card",
    "delivery_type": "delivery",
    "delivery_date": "2025-12-15",
    "delivery_time": "10:00-14:00",
    "comment": "Комментарий к заказу",
    "address": {
        "city": "Москва",
        "street": "Ленина",
        "house": "10",
        "apartment": "5"
    },
    "items": [
        {
            "product_id": 1,
            "sku": "PROD-001",
            "name": "Товар 1",
            "quantity": 2,
            "price": 5000.0,
            "total": 10000.0
        }
    ],
    "created_at": "2025-12-11T10:00:00Z"
}
```

## Управление остатками

### Проверка остатков

```php
use App\Actions\Inventory\Validation\ValidateStockAction;

$validateAction = app(ValidateStockAction::class);
$result = $validateAction->execute();

if (!$result['valid']) {
    // Обработка ошибок
    foreach ($result['errors'] as $error) {
        // $error['product_id'], $error['requested'], $error['available']
    }
}
```

### Уменьшение остатков

```php
use App\Actions\Inventory\Stock\DecreaseStockAction;

$decreaseAction = app(DecreaseStockAction::class);
$results = $decreaseAction->execute($order);
```

### Увеличение остатков

```php
use App\Actions\Inventory\Stock\IncreaseStockAction;

$increaseAction = app(IncreaseStockAction::class);
$results = $increaseAction->execute($order);
```

## Расширение системы

### Добавление новой интеграции

1. Создайте класс, реализующий `InventorySyncInterface`:

```php
namespace App\Services\Inventory\Integrations;

use App\Services\Inventory\Contracts\InventorySyncInterface;
use App\Models\Order\Order;

class MyCustomIntegration implements InventorySyncInterface
{
    public function syncOrderCreated(Order $order): bool
    {
        // Ваша логика
    }

    // ... остальные методы
}
```

2. Зарегистрируйте в `AppServiceProvider`:

```php
$this->app->singleton(InventorySyncInterface::class, function ($app) {
    return new MyCustomIntegration();
});
```

## Логирование

Все операции логируются:

-   Уменьшение остатков: `Stock decreased`
-   Увеличение остатков: `Stock increased`
-   Синхронизация с 1С: `Order synced to 1C`
-   Ошибки синхронизации: `Failed to sync order to 1C`

Логи доступны в `storage/logs/laravel.log`.

## Очереди

Слушатели событий используют очереди (`ShouldQueue`), что позволяет:

-   Не блокировать создание заказа
-   Обрабатывать синхронизацию асинхронно
-   Повторять неудачные попытки автоматически

Настройте очередь в `.env`:

```env
QUEUE_CONNECTION=database
```

Запустите воркер:

```bash
php artisan queue:work
```

## Тестирование

Для тестирования без реальной интеграции с 1С:

1. Отключите интеграцию: `ONEC_API_ENABLED=false`
2. Или создайте mock-реализацию `InventorySyncInterface`

## Безопасность

-   API ключ 1С хранится в `.env` (не коммитится)
-   Все запросы к 1С используют HTTPS
-   Таймауты предотвращают зависания
-   Ошибки логируются, но не прерывают основной процесс
