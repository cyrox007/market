# Система доставки (Shipping Locations + Carriers + Delivery Handling)

## Обзор

Система доставки построена на единой таблице **`shipping_locations`** с иерархией (parent/children) и наследованием цен/настроек.
Интегрирована с **Vanilo Shipping** для работы со **`carriers`** и **`shipping_methods`**.

## Архитектура

### Иерархия локаций (единая модель)

`shipping_locations` поддерживает типы:

-   `federal_district` — федеральный округ
-   `region` — регион
-   `locality` — населённый пункт

### Наследование цен

Цены и свойства наследуются по иерархии снизу вверх:

-   Если у города нет цены → берется цена региона
-   Если у региона нет цены → берется цена федерального округа
-   Если у федерального округа нет цены → возвращается null

### Типы обработки доставки (подъём/разгрузка)

Используется система **типов обработки доставки** (`delivery_handling_types`) + pivot
`shipping_location_delivery_handling` с ценами и правилами.

Примеры:

1. **Лифт** (`code=elevator`) — фиксированная цена
2. **Ручной подъем** (`code=manual`) — цена может зависеть от этажа

Для каждой локации можно настроить:

-   `base_price`
-   `elevator_price`
-   `floor_prices` (JSON с ценой по этажам)
-   `requires_floor` у типа обработки

## Модели

### ShippingLocation

-   `getEffectiveDeliveryPrice()`
-   `getEffectiveFreeDeliveryThreshold()`
-   `getEffectiveDeliveryDays()`
-   `getEffectiveAssemblyPrice()`
-   `getEffectiveRequiresAssembly()`
-   `getDeliveryHandlingPrice(DeliveryHandlingType $type, ?int $floor)`
-   `getEffectiveCarriers()` (наследование carriers)

### DeliveryHandlingType

-   `requires_floor` — требует ли обязательного указания этажа

## Сервисы

### ShippingCalculationService

```php
// Рассчитать стоимость доставки (включает delivery + handling + assembly в total)
$calculation = $service->calculateShipping(
    $location,
    $orderAmount,
    $handlingType,
    $floor
);

// Проверить доступность доставки
$availability = $service->checkDeliveryAvailability(
    $locality,
    $orderAmount,
    $orderWeight,
    $orderVolume
);
```

## API Endpoints

### GET /api/v1/shipping/locations

Список локаций с фильтрами `type`, `parent_id`, `search`

### GET /api/v1/shipping/locations/tree

Дерево локаций (`active_children`)

### GET /api/v1/shipping/locations/{locationId}

Информация о локации + carriers + shipping_methods + handling types

### POST /api/v1/shipping/calculate

Рассчитать стоимость доставки

```json
{
    "location_id": 1,
    "order_amount": 5000,
    "delivery_handling_type_id": 2,
    "floor": 5,
    "requires_assembly": true
}
```

### GET /api/v1/shipping/delivery-handling-types

Список доступных типов обработки доставки

### GET /api/v1/shipping/carriers

Список carriers (Vanilo)

### GET /api/v1/shipping/shipping-methods?location_id={id}&order_amount={amount}

Список доступных shipping methods для локации (Vanilo), с расчётом цены по сумме заказа

### POST /api/v1/shipping/shipping-methods/calculate

Расчёт цены для конкретного shipping method

## Интеграция с Vanilo

Система интегрирована с Vanilo Shipping:

-   Локации могут иметь **наследуемые carriers** (`carrier_shipping_location`)
-   Для локации автоматически создаются **shipping methods** (через `CarrierService`), если их нет
-   Используются таблицы Vanilo: `carriers`, `shipping_methods`

## Заполнение данных

```bash
# Запустить миграции
docker exec sv_app php artisan migrate

# Заполнить локации/типы обработки/перевозчиков
docker exec sv_app php artisan db:seed
```

## Пример использования

```php
use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\DeliveryHandlingType;
use App\Services\Shipping\ShippingCalculationService;

$location = ShippingLocation::find(1);
$handlingType = DeliveryHandlingType::where('code', 'manual')->first();
$service = new ShippingCalculationService();

$result = $service->calculateShipping($location, 5000, $handlingType, 5);

// Результат:
// [
//   'delivery_price' => 1500.00,
//   'handling_price' => 1100.00,
//   'total' => 2600.00,
//   'free_delivery_threshold' => 10000.00,
//   'delivery_days' => ['min' => 3, 'max' => 7],
//   'shipping_method' => ShippingMethod
// ]
```

## Особенности для мебельного магазина

-   Поддержка сборки мебели (`requires_assembly`, `assembly_price`, `assembly_days`)
-   Ограничения по весу и объему заказа
-   Минимальная сумма заказа для доставки
-   Гибкая настройка цен разгрузки по этажам
