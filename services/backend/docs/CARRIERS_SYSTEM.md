# Система служб доставки (Carriers)

## Обзор

Система служб доставки интегрирована с Vanilo Shipping и позволяет назначать несколько служб доставки (carriers) на каждую локацию доставки с индивидуальными настройками цен и сроков.

## Архитектура

### Связь Carriers и Shipping Locations

-   **Many-to-Many связь** между `carriers` (Vanilo) и `shipping_locations`
-   Каждая связь может иметь индивидуальные настройки:
    -   Базовая цена доставки
    -   Порог бесплатной доставки
    -   Сроки доставки (мин/макс)
    -   Порядок сортировки
    -   Активность

### Наследование

Carriers наследуются по иерархии локаций:

-   Если у города нет carriers → используются carriers региона
-   Если у региона нет carriers → используются carriers федерального округа
-   Если нигде нет carriers → создаются демонстрационные shipping methods автоматически

### Автоматическое создание Shipping Methods

Сервис `CarrierService` автоматически создает shipping methods на основе:

1. Carriers, привязанных к локации (с учетом наследования)
2. Настроек локации (цена, сроки, порог бесплатной доставки)
3. Демонстрационных carriers, если нет привязанных

## Установка

### 1. Запустить миграции

```bash
docker exec sv_app php artisan migrate
```

### 2. Заполнить демонстрационные данные

```bash
docker exec sv_app php artisan db:seed
```

Это создаст:

-   Демонстрационные carriers (Стандартная доставка, Экспресс-доставка, Доставка в регионы)
-   Типы обработки доставки
-   Локации доставки

## Использование

### API Endpoints

#### Получить список carriers

```
GET /api/v1/shipping/carriers
```

#### Получить shipping methods для локации

```
GET /api/v1/shipping/shipping-methods?location_id={id}&order_amount={amount}
```

#### Рассчитать стоимость доставки для shipping method

```
POST /api/v1/shipping/shipping-methods/calculate
{
  "shipping_method_id": 1,
  "location_id": 1,
  "order_amount": 5000,
  "delivery_handling_type_id": 1,
  "floor": 5
}
```

### Filament Admin

1. **Управление Carriers**: `/admin/carriers`

    - Создание и редактирование служб доставки
    - Настройка конфигурации

2. **Управление Carriers для локаций**: `/admin/shipping-locations/{id}/edit`
    - Вкладка "Службы доставки"
    - Привязка carriers к локациям
    - Настройка индивидуальных цен и сроков

## Примеры использования

### PHP (Backend)

```php
use App\Services\Shipping\CarrierService;
use App\Models\Shipping\ShippingLocation;

$location = ShippingLocation::find(1);
$carrierService = new CarrierService();

// Получить доступные shipping methods
$methods = $carrierService->getAvailableShippingMethods($location);

// Рассчитать стоимость
$price = $carrierService->calculateShippingMethodPrice(
    $shippingMethod,
    $location,
    5000.0
);
```

### JavaScript (Frontend)

```typescript
import { api } from "@/lib/api";

// Получить shipping methods для локации
const methods = await api.shipping.getShippingMethods({
    location_id: 1,
    order_amount: 5000,
});

// Рассчитать стоимость
const calculation = await api.shipping.calculateShippingMethod({
    shipping_method_id: 1,
    location_id: 1,
    order_amount: 5000,
});
```

## Тестирование

Запустить тесты:

```bash
docker exec sv_app php artisan test --filter ShippingControllerTest
```

## Особенности

1. **Наследование carriers**: Carriers наследуются от родительских локаций
2. **Автоматическое создание**: Shipping methods создаются автоматически при запросе
3. **Индивидуальные настройки**: Каждая связь carrier-location может иметь свои цены и сроки
4. **Интеграция с Vanilo**: Используются стандартные модели Vanilo Carrier и ShippingMethod
