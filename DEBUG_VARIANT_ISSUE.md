# Отладка проблемы с вариациями и правилами корзины

## Проблема
При выборе варианта товара (цвет + размер) не обновляются:
1. SKU (артикул) - должен быть из вариации
2. Цена - должна применяться через правила корзины для региона

## Что было исправлено

### 1. Логирование добавлено на всех этапах:

#### Backend:
- `ProductController::show` - логирует параметры запроса (color, size, region_id)
- `Product::getVariantByAttributes` - логирует поиск цвета и размера
- `ProductDetailResource::toArray` - логирует определение вариации и применение правил
- `ProductRegionRuleService::getPriceForRegion` - логирует поиск и применение правил

#### Frontend:
- `fetchProduct` - логирует запрос и ответ API

### 2. Улучшена логика поиска вариаций:

- **Цвет**: Теперь сравнивается как точное совпадение, так и по slug
  - Фронтенд может передать "Белая" или "belaia"
  - Бэкенд ищет в БД по полю `color` (хранится как "Белая", "Коричневая", "Серая")
  
- **Размер**: Улучшен парсинг размера
  - Фронтенд передает "210x160"
  - Бэкенд парсит и ищет по `length` и `width`

### 3. Исправлено получение данных вариации:

- SKU берется напрямую из атрибутов модели: `$this->attributes['sku']`
- Цена берется напрямую из атрибутов: `$this->attributes['price']`
- Это гарантирует, что используются данные вариации, а не родительского товара

## Как проверить

### 1. Проверьте логи Laravel:

```bash
tail -f storage/logs/laravel.log | grep -E "ProductController|Product::getVariantByAttributes|ProductDetailResource|ProductRegionRuleService"
```

### 2. Проверьте консоль браузера:

Откройте DevTools (F12) → Console и выберите вариант товара. Должны появиться логи:
- `🔍 fetchProduct - Запрос к API` - параметры запроса
- `✅ fetchProduct - Ответ от API` - данные из API

### 3. Проверьте API напрямую:

```bash
# Без варианта (должен вернуть родительский товар)
curl "http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant?region_id=4"

# С вариантом (должен вернуть вариацию)
curl "http://localhost:8000/api/v1/products/krovat-dvuspalnaia-elegant?region_id=4&color=Белая&size=210x160"
```

### 4. Что проверить в ответе API:

#### Без варианта (родительский товар):
```json
{
  "product": {
    "id": 28,
    "sku": "SV-VAR-002",
    "price": 34990,
    "is_variant": false,
    ...
  }
}
```

#### С вариантом (должна быть вариация):
```json
{
  "product": {
    "id": 29,  // ID вариации, не родительского товара!
    "sku": "SV-VAR-002-WHITE",  // SKU вариации
    "price": 20000,  // Цена с применением правил корзины (если правило установлено)
    "is_variant": true,  // Должно быть true!
    ...
  }
}
```

## Возможные проблемы

### 1. Вариация не находится

**Признаки:**
- В логах: `ProductController::show - Вариация не найдена`
- В ответе API: `is_variant: false`

**Причины:**
- Неправильный формат цвета или размера
- Цвет/размер не совпадает с данными в БД

**Решение:**
- Проверьте логи `Product::getVariantByAttributes - Поиск цвета/размера`
- Убедитесь, что в БД есть вариация с такими цветом и размером

### 2. Правила корзины не применяются

**Признаки:**
- В ответе API цена не меняется (остается базовая)
- В логах: `ProductRegionRuleService::getPriceForRegion - Правила не найдены`

**Причины:**
- Правило не установлено для этого региона
- Правило установлено для родительского товара, а не для вариации
- `region_id` не передается в запросе

**Решение:**
- Проверьте, что `region_id` передается в запросе
- Проверьте, что правило установлено в админке для вариации (ID вариации, не родительского товара!)

### 3. SKU не обновляется

**Признаки:**
- В ответе API SKU остается от родительского товара
- `is_variant: false` в ответе

**Причины:**
- Вариация не находится (см. проблему 1)
- `ProductDetailResource` получает родительский товар вместо вариации

**Решение:**
- Проверьте логи `ProductController::show - Вариация найдена`
- Убедитесь, что вариация возвращается из контроллера

## SQL запросы для проверки

### Найти все вариации товара:
```sql
SELECT 
    id,
    sku,
    color,
    length,
    width,
    price,
    parent_product_id
FROM products
WHERE parent_product_id = 28  -- ID родительского товара
  AND state = 'active';
```

### Найти правила корзины для вариации:
```sql
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
WHERE prr.variant_id = 29  -- ID вариации
  AND prr.is_active = 1
ORDER BY prr.priority DESC, prr.id DESC;
```

### Найти регион Волжский:
```sql
SELECT id, name, type, is_active
FROM shipping_locations
WHERE name LIKE '%Волжский%'
  AND is_active = 1;
```

## Следующие шаги

1. Выберите вариант товара на фронтенде
2. Проверьте логи Laravel - должны быть записи на каждом этапе
3. Проверьте ответ API - должен вернуть вариацию с правильным SKU и ценой
4. Если проблема остается, пришлите логи из Laravel и консоли браузера
