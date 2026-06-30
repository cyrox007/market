# Примеры запросов к API товара для отладки

## Базовый URL
```
http://localhost:8000/api/v1
```

## Примеры запросов

### 1. Товар без региона (базовая цена)
```bash
curl -X GET "http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

Или в браузере:
```
http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant
```

### 2. Товар с регионом Волжский (ID нужно узнать из БД)
```bash
curl -X GET "http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant?region_id=4" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

Или в браузере:
```
http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant?region_id=4
```

### 3. Товар с регионом и выбранным вариантом (цвет и размер)
```bash
curl -X GET "http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant?region_id=4&color=Белый&size=200x160" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

Или в браузере:
```
http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant?region_id=4&color=Белый&size=200x160
```

### 4. Товар с регионом и только цветом
```bash
curl -X GET "http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant?region_id=4&color=Белый" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

### 5. Товар с регионом и только размером
```bash
curl -X GET "http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant?region_id=4&size=200x160" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

## Как узнать ID региона Волжский

### Через API регионов:
```bash
curl -X GET "http://localhost:8000/api/v1/regions/tree" \
  -H "Accept: application/json"
```

Или в браузере:
```
http://localhost:8000/api/v1/regions/tree
```

### Через SQL запрос:
```sql
SELECT id, name, type FROM shipping_locations WHERE name LIKE '%Волжский%' AND is_active = 1;
```

## Что проверять в ответе

### 1. Без региона (должна быть базовая цена):
```json
{
  "product": {
    "id": 123,
    "sku": "ART-123",
    "price": 15000.00,
    "name": "...",
    ...
  }
}
```

### 2. С регионом Волжский (должна быть цена 20000 по правилу):
```json
{
  "product": {
    "id": 456,  // ID вариации, если выбраны color и size
    "sku": "ART-456",  // SKU вариации
    "price": 20000.00,  // Цена с применением правил корзины
    "name": "...",
    "is_variant": true,  // Если выбрана вариация
    ...
  }
}
```

## Проверка логов Laravel

После каждого запроса проверяйте логи:
```bash
tail -f storage/logs/laravel.log | grep -E "ProductController|ProductDetailResource|ProductRegionRuleService"
```

Или для более детального просмотра:
```bash
tail -f storage/logs/laravel.log
```

## Примеры для разных регионов

### Москва (если есть правило):
```bash
curl -X GET "http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant?region_id=1&color=Белый&size=200x160"
```

### Санкт-Петербург (если есть правило):
```bash
curl -X GET "http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant?region_id=2&color=Белый&size=200x160"
```

## Отладка через Postman

1. Метод: `GET`
2. URL: `http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant`
3. Query Params:
   - `region_id`: `4` (или другой ID региона)
   - `color`: `Белый` (или другой цвет)
   - `size`: `200x160` (или другой размер)

## Отладка через браузер

Просто откройте в браузере:
```
http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant?region_id=4&color=Белый&size=200x160
```

Установите расширение для форматирования JSON (например, JSON Formatter для Chrome).

## Проверка правил корзины в БД

```sql
-- Найти правила для товара в регионе Волжский
SELECT 
    prr.id,
    prr.product_id,
    prr.variant_id,
    prr.shipping_location_id,
    sl.name as location_name,
    prr.price_type,
    prr.price_value,
    prr.is_active
FROM product_region_rules prr
JOIN shipping_locations sl ON prr.shipping_location_id = sl.id
WHERE sl.name LIKE '%Волжский%'
  AND prr.is_active = 1
ORDER BY prr.priority DESC, prr.id DESC;
```

## Важные моменты для отладки

1. **SKU вариации** должен быть из самой вариации, а не из родительского товара
2. **Цена** должна применяться через `ProductRegionRuleService::getPriceForRegion`
3. **region_id** должен передаваться в query параметрах
4. **color и size** должны быть в правильном формате (цвет - название, размер - "200x160")

## Проверка через консоль Laravel

```bash
php artisan tinker
```

```php
// Найти регион Волжский
$region = \App\Models\Shipping\ShippingLocation::where('name', 'like', '%Волжский%')->first();
echo "Region ID: " . $region->id . "\n";

// Найти товар
$product = \App\Models\Product\Product::where('slug', 'krovat-dvuspalnaia-elegant')->first();
echo "Product ID: " . $product->id . "\n";

// Найти вариацию
$variant = $product->getVariantByAttributes('Белый', '200x160');
if ($variant) {
    echo "Variant ID: " . $variant->id . "\n";
    echo "Variant SKU: " . $variant->sku . "\n";
    echo "Variant Price: " . $variant->price . "\n";
    
    // Применить правила
    $service = app(\App\Services\Product\ProductRegionRuleService::class);
    $priceWithRules = $service->getPriceForRegion($product, $region, $variant);
    echo "Price with rules: " . $priceWithRules . "\n";
}
```
