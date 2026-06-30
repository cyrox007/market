# Руководство по сидерам для базового функционала

## Минимальный набор сидеров для запуска

Для базового функционала интернет-магазина необходимо выполнить следующие сидеры:

### 1. Обязательные сидеры (критичные)

```bash
# Регионы и доставка
php artisan db:seed --class=RussianRegionsSeeder
php artisan db:seed --class=DeliveryHandlingTypesSeeder
php artisan db:seed --class=CarriersSeeder
php artisan db:seed --class=ShippingLocationsSeeder

# Методы оплаты (критично для оформления заказа)
php artisan db:seed --class=PaymentMethodSeeder

# Каталог товаров
php artisan db:seed --class=FurnitureCatalogSeeder

# Роли и разрешения (для админ-панели)
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### 2. Привязка методов доставки и оплаты (после NewShippingLocationsSeeder)

После выполнения `NewShippingLocationsSeeder` необходимо привязать методы доставки и оплаты:

```bash
php artisan db:seed --class=AttachShippingAndPaymentMethodsSeeder
```

Этот сидер:

- Привязывает все активные методы оплаты к регионам
- Привязывает все активные методы доставки (carriers) к регионам
- Методы наследуются дочерними локациями (города, населенные пункты)

### 3. Рекомендуемые сидеры (для полного функционала)

```bash
# Вариации товаров
php artisan db:seed --class=ProductVariantsSeeder

# По 3 фото в галерею каждому товару (локальные файлы или URL-заглушки)
php artisan db:seed --class=ProductGalleryPhotosSeeder

# Атрибуты товаров (цвет, размер и т.д.)
php artisan db:seed --class=ProductAttributesSeeder

# Атрибуты вариаций по умолчанию для категорий (для демо)
php artisan db:seed --class=CategoryVariationAttributesSeeder

# Подборки товаров
php artisan db:seed --class=ProductCollectionSeeder

# Сопутствующие товары (до 4 на каждый основной товар)
php artisan db:seed --class=ProductRelatedSeeder

# Блоки характеристик и доставки для товаров
php artisan db:seed --class=ProductBlocksSeeder

# Дополнительные услуги (сборка, подъем и т.д.)
php artisan db:seed --class=AdditionalServicesSeeder

# Магазины
php artisan db:seed --class=StoresSeeder

# Идеи для интерьера
php artisan db:seed --class=InteriorIdeasSeeder

# Страница "О нас"
php artisan db:seed --class=AboutPageSeeder

# Почтовые события
php artisan db:seed --class=MailEventsSeeder
```

## Быстрый запуск всех сидеров

Для полной инициализации базы данных:

```bash
php artisan db:seed
```

Это выполнит все сидеры, указанные в `DatabaseSeeder.php`.

## Минимальная команда для базового функционала

Если нужно только самое необходимое:

```bash
php artisan db:seed --class=RussianRegionsSeeder
php artisan db:seed --class=DeliveryHandlingTypesSeeder
php artisan db:seed --class=CarriersSeeder
php artisan db:seed --class=ShippingLocationsSeeder
php artisan db:seed --class=PaymentMethodSeeder
php artisan db:seed --class=AttachShippingAndPaymentMethodsSeeder
php artisan db:seed --class=FurnitureCatalogSeeder
php artisan db:seed --class=RolesAndPermissionsSeeder
```

**Или если используете NewShippingLocationsSeeder:**

```bash
php artisan db:seed --class=DeliveryHandlingTypesSeeder
php artisan db:seed --class=CarriersSeeder
php artisan db:seed --class=NewShippingLocationsSeeder
php artisan db:seed --class=PaymentMethodSeeder
php artisan db:seed --class=AttachShippingAndPaymentMethodsSeeder
php artisan db:seed --class=FurnitureCatalogSeeder
php artisan db:seed --class=RolesAndPermissionsSeeder
```

## Описание сидеров

### RussianRegionsSeeder

- **Назначение**: Создает федеральные округа, регионы и города России
- **Критичность**: Высокая - нужен для выбора региона доставки
- **Зависимости**: Нет

### DeliveryHandlingTypesSeeder

- **Назначение**: Типы обработки доставки (подъем на этаж, сборка и т.д.)
- **Критичность**: Высокая - нужен для расчета стоимости доставки
- **Зависимости**: Нет

### CarriersSeeder

- **Назначение**: Службы доставки (курьер, самовывоз и т.д.)
- **Критичность**: Высокая - нужен для выбора способа доставки
- **Зависимости**: Нет

### ShippingLocationsSeeder

- **Назначение**: Локации доставки (федеральные округа и регионы)
- **Критичность**: Высокая - нужен для расчета доставки
- **Зависимости**: DeliveryHandlingTypesSeeder (желательно)

### PaymentMethodSeeder

- **Назначение**: Методы оплаты (карта, наличные, рассрочка)
- **Критичность**: Высокая - без этого нельзя оформить заказ
- **Зависимости**: Нет

### FurnitureCatalogSeeder

- **Назначение**: Категории товаров и базовые товары
- **Критичность**: Высокая - без этого нет каталога
- **Зависимости**: Нет

### ProductVariantsSeeder

- **Назначение**: Вариации товаров (разные цвета, размеры)
- **Критичность**: Средняя - расширяет функционал товаров
- **Зависимости**: FurnitureCatalogSeeder

### CategoryProductsSeeder

- **Назначение**: В каждой категории по 20 товаров на основе существующих (с фото). Клонирует товары с копированием медиа. Нужен для проверки пагинации.
- **Критичность**: Низкая - для тестовых данных
- **Зависимости**: FurnitureCatalogSeeder, ProductVariantsSeeder (желательно — для товаров с фото)

### ProductAttributesSeeder

- **Назначение**: Атрибуты товаров (цвет, размер, материал)
- **Критичность**: Средняя - нужен для фильтров
- **Зависимости**: FurnitureCatalogSeeder

### CategoryVariationAttributesSeeder

- **Назначение**: Добавляет атрибуты вариаций категориям по умолчанию (для демо-данных)
- **Критичность**: Низкая - для демонстрации функционала предвыбора атрибутов
- **Зависимости**: 
    - `FurnitureCatalogSeeder` (должен быть выполнен первым - нужны категории)
    - `ProductAttributesSeeder` (должен быть выполнен первым - нужны атрибуты)
- **Что делает**:
    - Всем категориям добавляет атрибут **"Цвет"**
    - Категориям с "диван" в названии добавляет **"Размер"**
    - Категориям с "угловой" добавляет **"Материал"** и **"Комплект"**
    - Категориям с "шкаф" или "комод" добавляет **"Размер"**
- **Примечание**: При создании товаров в этих категориях атрибуты будут автоматически предвыбраны в форме выбора атрибутов вариаций. Товары всё равно могут иметь свои дополнительные атрибуты или убрать предвыбранные.

### ProductCollectionSeeder

- **Назначение**: Подборки товаров (новинки, акции и т.д.)
- **Критичность**: Низкая - для главной страницы
- **Зависимости**: FurnitureCatalogSeeder

### ProductRelatedSeeder

- **Назначение**: Генерация случайных сопутствующих товаров (до 4 на каждый основной товар)
- **Критичность**: Низкая - для блока «С этим товаром покупают» на странице товара
- **Зависимости**: FurnitureCatalogSeeder

### ProductBlocksSeeder

- **Назначение**: Создает и наполняет два типа блоков для карточки товара:
    - **Блоки фич товара** (иконки с короткими преимуществами: быстрая доставка, гарантия, сборка, возврат и т.п.)
    - **Блоки доставки** (развернутые описания условий доставки по городу, в регионы, сборки и т.д.)
  Привязывает эти блоки к случайному подмножеству активных категорий (через pivot‑таблицы `category_feature_blocks` и `category_delivery_blocks`) с сортировкой, чтобы на карточках товаров в этих категориях отображались секции «Фичи товара» и «Доставка».
- **Критичность**: Низкая - для наполнения детальных страниц товара маркетинговыми блоками, не критично для базовой работы магазина
- **Зависимости**: 
    - `FurnitureCatalogSeeder` (нужны активные категории для привязки блоков)

### AdditionalServicesSeeder

- **Назначение**: Дополнительные услуги (сборка, подъем)
- **Критичность**: Средняя - для оформления заказа
- **Зависимости**: Нет

### StoresSeeder

- **Назначение**: Магазины (для самовывоза)
- **Критичность**: Низкая - только если используется самовывоз
- **Зависимости**: Нет

### InteriorIdeasSeeder

- **Назначение**: Идеи для интерьера с горячими точками
- **Критичность**: Низкая - для главной страницы
- **Зависимости**: FurnitureCatalogSeeder

### AboutPageSeeder

- **Назначение**: Контент страницы "О нас"
- **Критичность**: Низкая - только для страницы "О нас"
- **Зависимости**: Нет

### RolesAndPermissionsSeeder

- **Назначение**: Роли и разрешения для админ-панели
- **Критичность**: Высокая - для работы админки
- **Зависимости**: Нет

### MailEventsSeeder

- **Назначение**: Почтовые события для уведомлений
- **Критичность**: Низкая - только для email-уведомлений
- **Зависимости**: Нет

### AttachShippingAndPaymentMethodsSeeder

- **Назначение**: Привязывает методы доставки и оплаты к локациям доставки
- **Критичность**: Высокая - без этого методы оплаты и доставки не будут доступны для регионов
- **Зависимости**:
    - PaymentMethodSeeder (должен быть выполнен первым)
    - CarriersSeeder (должен быть выполнен первым)
    - NewShippingLocationsSeeder или ShippingLocationsSeeder (должен быть выполнен первым)

## Порядок выполнения

Рекомендуемый порядок выполнения сидеров:

1. **Регионы и доставка:**

    ```bash
    php artisan db:seed --class=RussianRegionsSeeder
    php artisan db:seed --class=DeliveryHandlingTypesSeeder
    php artisan db:seed --class=CarriersSeeder
    php artisan db:seed --class=ShippingLocationsSeeder
    ```

2. **Оплата:**

    ```bash
    php artisan db:seed --class=PaymentMethodSeeder
    ```

3. **Каталог:**

    ```bash
    php artisan db:seed --class=FurnitureCatalogSeeder
    php artisan db:seed --class=ProductAttributesSeeder
    php artisan db:seed --class=CategoryVariationAttributesSeeder
    php artisan db:seed --class=ProductVariantsSeeder
    php artisan db:seed --class=CategoryProductsSeeder
    php artisan db:seed --class=ProductRelatedSeeder
    ```

4. **Дополнительно:**

    ```bash
    php artisan db:seed --class=ProductCollectionSeeder
    php artisan db:seed --class=ProductBlocksSeeder
    php artisan db:seed --class=AdditionalServicesSeeder
    php artisan db:seed --class=StoresSeeder
    php artisan db:seed --class=InteriorIdeasSeeder
    php artisan db:seed --class=AboutPageSeeder
    ```

5. **Привязка методов доставки и оплаты:**

    ```bash
    php artisan db:seed --class=AttachShippingAndPaymentMethodsSeeder
    ```

6. **Админка:**
    ```bash
    php artisan db:seed --class=RolesAndPermissionsSeeder
    php artisan db:seed --class=MailEventsSeeder
    ```

## Проверка после выполнения

После выполнения сидеров проверьте:

1. **Регионы доступны:**

    ```bash
    php artisan tinker
    >>> App\Models\Shipping\Region::count()
    ```

2. **Товары созданы:**

    ```bash
    >>> App\Models\Product\Product::count()
    ```

3. **Методы оплаты созданы:**

    ```bash
    >>> App\Models\Payment\PaymentMethod::count()
    ```

4. **Роли созданы:**
    ```bash
    >>> Spatie\Permission\Models\Role::count()
    ```

## Создание администратора

После выполнения сидеров создайте администратора с полными правами:

### Способ 1: Через Artisan команду (рекомендуется)

```bash
# Создать нового админа с указанным паролем
php artisan admin:create admin@example.com --name="Admin Name" --password=your_password

# Создать нового админа (пароль будет сгенерирован автоматически)
php artisan admin:create admin@example.com --name="Admin Name"

# Назначить роль super_admin существующему пользователю
php artisan admin:create existing@example.com
```

### Способ 2: Через скрипт

```bash
# Для существующего пользователя
php assign_super_admin.php admin@example.com

# Создать нового админа
php assign_super_admin.php admin@example.com "Admin Name" password123
```

### Способ 3: Через Tinker

```bash
php artisan tinker
```

```php
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

// Создать пользователя
$user = User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => Hash::make('your_password'),
]);

// Назначить роль super_admin
$role = Role::where('name', 'super_admin')->first();
$user->assignRole($role);
```

### Способ 4: Через админ-панель Filament

1. Войдите в админку: `http://your-domain.com/admin_sv`
2. Перейдите в раздел **"Пользователи"**
3. Создайте нового пользователя или откройте существующего
4. В разделе **"Роли"** выберите роль **"super_admin"**
5. Сохраните

## Примечания

- Сидеры можно выполнять многократно - они используют `updateOrCreate`, поэтому не создадут дубликаты
- Если нужно очистить данные перед сидированием, используйте миграции с `down()` методом
- Для продакшена рекомендуется выполнить все сидеры для полного функционала
- **Важно**: Перед созданием админа убедитесь, что выполнен сидер `RolesAndPermissionsSeeder`
