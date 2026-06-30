# Система вариаций и характеристик товаров

## Обзор

Система позволяет создавать вариативные товары с настраиваемыми атрибутами вариаций (цвет, размер, комплект и т.д.). Вариации привязаны к значениям атрибутов через таблицу `product_variant_attributes`. Идентификация вариаций в API и корзине выполняется через массив `variation_attributes` (пары attribute_slug + value_slug).

## Структура базы данных

### Таблицы характеристик
- `product_attributes` — атрибуты (Цвет, Размер, Комплект и т.д.); поле `is_use_in_variations` определяет, участвует ли атрибут в вариациях
- `product_attribute_values` — значения атрибутов (Серый, 180x95 и т.д.)
- `product_product_attributes` — связь продуктов с характеристиками (для обычных товаров)
- `product_variant_attributes` — связь вариаций с значениями атрибутов (variant_id, attribute_value_id)

### Поля в таблице products
- `is_variable` — является ли товар вариативным
- `parent_product_id` — ID родительского товара (для вариаций)

## Использование в админке

### 1. Атрибуты для вариаций

1. Раздел **«Товары» → «Характеристики»**
2. Создайте атрибут (например, «Цвет», «Размер», «Комплект») и отметьте **«Использовать в вариациях»** (`is_use_in_variations`)
3. Добавьте значения атрибута (например, для Цвета: Серый, Синий; для Размера: 180x95, 200x100)

Только атрибуты с включённым «Использовать в вариациях» попадают в форму торговых предложений и в API как `variation_attributes`.

### 2. Вариативный товар и торговые предложения

1. Создайте товар в **«Товары»** и включите переключатель **«Вариативный товар»**
2. Сохраните товар и откройте вкладку **«Вариации товара»** (или «Торговые предложения»)
3. Нажмите **«Добавить торговое предложение»**: в форме появятся поля для каждого атрибута вариаций (Цвет, Размер и т.д.) — выберите значения, укажите цену, SKU, остаток, при необходимости изображения
4. После сохранения выбранные значения записываются в таблицу `product_variant_attributes` и используются в API и на сайте для идентификации вариации

В таблице торговых предложений колонка **«Параметры»** показывает текущие значения атрибутов вариации по данным из `product_variant_attributes`.

### 3. Объединение нескольких товаров в вариативный (список товаров)

1. В **«Товары»** отметьте чекбоксами **минимум 2** простых товара (без родителя, без существующих торговых предложений).
2. **Действия → «Объединить в вариативный товар»**.
3. Выберите **родительский товар** (карточка на сайте, аналог товара в Битрикс) и при необходимости скорректируйте **название вариации** для каждого торгового предложения.
4. **Атрибуты вариаций** для родителя определяются автоматически (как при ручном создании ТП): из привязки к товару, категории или глобального справочника; обязательно включается атрибут **«Вариант»**.
5. Каждое бывшее простое предложение становится ТП: главное отличие — значение «Вариант» (по умолчанию название товара); цвет/размер и др. переносятся из характеристик карточки, если они помечены «использовать в вариациях».
6. После подтверждения выбранные товары (кроме родителя) получают `parent_product_id`; родитель — `is_variable = true`.

**Сохраняется при объединении:**

- `external_id` 1С, SKU, slug, цены, остатки, `product_warehouse_stocks`, медиа, заказы и корзина (тот же `product_id`).
- Синхронизация 1С по `external_id` и складским остаткам продолжает работать: записи не пересоздаются, меняется только иерархия parent/variant.

**Ограничения:**

- Нельзя объединять торговые предложения или товары, у которых уже есть вариации.
- До 20 товаров за одну операцию.
- Товары-варианты выпадают из подборок/связей, где показываются только родители (`parent_product_id IS NULL`).
- Старые URL slug вариантов остаются рабочими.

## API

### Получение товара (родитель или конкретная вариация)

```http
GET /api/v1/products/{slug}?region_id=558
GET /api/v1/products/{slug}?region_id=558&attributes[color]=seryi&attributes[size]=180x95
```

Параметры:
- `region_id` — регион для цен и наличия
- `attributes[attribute_slug]=value_slug` — для вариативного товара возвращает конкретную вариацию с этими атрибутами

### Ответ ProductDetailResource (страница товара)

Для вариативного товара в ответе есть:

- **variation_attributes** — список атрибутов, участвующих в вариациях, с доступными значениями:
  - `attribute_slug`, `attribute_name`, `type`
  - `values`: массив `{ id, name, slug, code? }`
- **selected_variation** — выбранная комбинация (для текущей вариации или самой дешёвой): массив `{ attribute_slug, value_slug, value_name, code? }`
- **variants** — массив вариаций, каждая с полями: `id`, `sku`, `price`, `old_price`, `stock`, `in_stock`, **variation_attributes** (массив как selected_variation), `images`

Пример:

```json
{
  "id": 1,
  "name": "Диван угловой",
  "is_variable": true,
  "variation_attributes": [
    {
      "attribute_slug": "color",
      "attribute_name": "Цвет",
      "type": "color",
      "values": [
        { "id": 1, "name": "Серый", "slug": "seryi", "code": "#808080" }
      ]
    },
    {
      "attribute_slug": "size",
      "attribute_name": "Размер",
      "values": [
        { "id": 5, "name": "180 x 95 см", "slug": "180x95" }
      ]
    }
  ],
  "selected_variation": [
    { "attribute_slug": "color", "value_slug": "seryi", "value_name": "Серый", "code": "#808080" },
    { "attribute_slug": "size", "value_slug": "180x95", "value_name": "180 x 95 см" }
  ],
  "variants": [
    {
      "id": 101,
      "sku": "DIV-UGL-seryi-180x95",
      "price": 45990,
      "variation_attributes": [
        { "attribute_slug": "color", "value_slug": "seryi", "value_name": "Серый" },
        { "attribute_slug": "size", "value_slug": "180x95", "value_name": "180 x 95 см" }
      ],
      "images": ["..."],
      "stock": 5,
      "in_stock": true
    }
  ]
}
```

### Корзина

**Добавление в корзину:**

```http
POST /api/v1/cart
Content-Type: application/json

{
  "product_id": 1,
  "quantity": 2,
  "variation_attributes": [
    { "attribute_slug": "color", "value_slug": "seryi" },
    { "attribute_slug": "size", "value_slug": "180x95" }
  ]
}
```

Для вариативного товара вариация определяется по полному совпадению `variation_attributes`. Если товар не вариативный, `variation_attributes` не передаётся.

**Обновление позиции (в т.ч. смена вариации):**

```http
PUT /api/v1/cart/{itemId}
Content-Type: application/json

{
  "quantity": 3,
  "variation_attributes": [
    { "attribute_slug": "color", "value_slug": "sinii" },
    { "attribute_slug": "size", "value_slug": "200x100" }
  ]
}
```

**Элемент корзины (CartItemResource)** содержит:
- `variation_attributes` — массив `{ attribute_slug, value_slug, value_name, code? }` для отображения выбранной вариации

## Модели и методы

### Product

- `getVariationAttributeSlugs()` — slug'и атрибутов с `is_use_in_variations`
- `getAvailableValuesForVariationAttribute(string $attributeSlug)` — доступные значения по активным вариациям
- `getVariantByVariationAttributes(array $attributes)` — вариация по массиву `['color' => 'seryi', 'size' => '180x95']`
- `getVariationAttributesForApi()` — массив в формате API (для вариации)

### Attribute

- Поле `is_use_in_variations` — участвует ли атрибут в вариациях
- Скоуп `variationAttributes()` — атрибуты с `is_use_in_variations = true`

## Фронтенд

1. **Страница товара:** опции выбора строятся по `product.variation_attributes` (для каждого атрибута — список `values`). Выбор пользователя хранится как комбинация attribute_slug → value_slug и сопоставляется с `product.variants[].variation_attributes` для отображения цены, остатка и фото. В корзину отправляется `variation_attributes`: `[{ attribute_slug, value_slug }, ...]`.
2. **Корзина:** текущая вариация позиции показывается из `item.variation_attributes`; смена вариации — через `PUT /cart/{id}` с новым `variation_attributes`.

## Сидеры

Порядок запуска:

1. `ProductAttributesSeeder` — создаёт атрибуты (в т.ч. Цвет, Размер, Комплект с `is_use_in_variations = true`) и их значения
2. `ProductVariantsSeeder` — создаёт вариации и заполняет `product_variant_attributes` через `syncVariantAttributes`

```bash
php artisan db:seed
# или
php artisan db:seed --class=ProductAttributesSeeder
php artisan db:seed --class=ProductVariantsSeeder
```
