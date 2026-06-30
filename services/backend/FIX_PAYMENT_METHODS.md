# Исправление ошибки с payment_methods

## Проблема

Ошибка: `Column not found: 1054 Unknown column 'code' in 'WHERE'` или `Unknown column 'is_active' in 'WHERE'`

В таблице `payment_methods` отсутствуют необходимые колонки (`code`, `is_active`, `icon`, `sort_order` и др.), которые используются в модели и сидере.

## Решение

### 1. Выполните миграцию (обновленную)

Миграция `2026_01_13_154051_create_payment_methods_table.php` была обновлена и теперь добавляет все недостающие колонки:

```bash
cd services/backend
php artisan migrate
```

Или если используете Docker:

```bash
docker exec sv_app php artisan migrate
```

### 2. Если миграции показывают "Nothing to migrate", но колонки отсутствуют

Выполните новую миграцию, которая принудительно добавит все недостающие колонки:

```bash
php artisan migrate --path=database/migrations/2026_01_23_140000_fix_payment_methods_table_structure.php
```

Эта миграция:
- Проверяет существующие колонки через прямой SQL
- Добавляет только те колонки, которых нет
- Безопасна для повторного выполнения

### 3. Альтернатива: Добавить колонки вручную через SQL

Если миграция не работает, добавьте колонки вручную:

```sql
-- Добавить code (критично для сидера)
ALTER TABLE `payment_methods` 
ADD COLUMN `code` VARCHAR(255) UNIQUE NULL 
COMMENT 'Уникальный код метода оплаты' 
AFTER `id`;

-- Добавить name
ALTER TABLE `payment_methods` 
ADD COLUMN `name` VARCHAR(255) NULL 
COMMENT 'Название метода оплаты' 
AFTER `code`;

-- Добавить description
ALTER TABLE `payment_methods` 
ADD COLUMN `description` TEXT NULL 
COMMENT 'Описание метода оплаты' 
AFTER `name`;

-- Добавить icon
ALTER TABLE `payment_methods` 
ADD COLUMN `icon` VARCHAR(255) NULL 
COMMENT 'Иконка метода оплаты' 
AFTER `description`;

-- Добавить is_enabled
ALTER TABLE `payment_methods` 
ADD COLUMN `is_enabled` BOOLEAN DEFAULT TRUE 
COMMENT 'Включен ли метод оплаты' 
AFTER `icon`;

-- Добавить is_active
ALTER TABLE `payment_methods` 
ADD COLUMN `is_active` BOOLEAN DEFAULT TRUE 
COMMENT 'Активен ли метод оплаты' 
AFTER `is_enabled`;

-- Добавить sort_order
ALTER TABLE `payment_methods` 
ADD COLUMN `sort_order` INT DEFAULT 0 
COMMENT 'Порядок сортировки' 
AFTER `is_active`;

-- Добавить gateway
ALTER TABLE `payment_methods` 
ADD COLUMN `gateway` VARCHAR(255) NULL 
COMMENT 'Платежный шлюз' 
AFTER `sort_order`;

-- Добавить configuration
ALTER TABLE `payment_methods` 
ADD COLUMN `configuration` JSON NULL 
COMMENT 'Конфигурация метода оплаты' 
AFTER `gateway`;
```

### 4. Обновить существующие записи

После добавления колонки обновите существующие методы оплаты:

```bash
php artisan tinker
```

```php
use App\Models\Payment\PaymentMethod;

// Установить is_active = true для всех методов оплаты (если колонка уже добавлена)
if (Schema::hasColumn('payment_methods', 'is_active')) {
    PaymentMethod::query()->update(['is_active' => true]);
}

// Установить is_enabled = true для всех методов оплаты
if (Schema::hasColumn('payment_methods', 'is_enabled')) {
    PaymentMethod::query()->update(['is_enabled' => true]);
}
```

### 5. Перезапустить сидер (опционально)

Если нужно пересоздать методы оплаты:

```bash
php artisan db:seed --class=PaymentMethodSeeder
```

## Проверка

После выполнения миграции проверьте:

```bash
php artisan tinker
```

```php
use App\Models\Payment\PaymentMethod;

// Проверить структуру таблицы
Schema::getColumnListing('payment_methods');

// Проверить методы оплаты
PaymentMethod::active()->get();
```

## Что было исправлено

1. ✅ Создана миграция для добавления колонки `is_active`
2. ✅ Обновлена существующая миграция для проверки наличия колонки
3. ✅ Сидер `PaymentMethodSeeder` уже устанавливает `is_active = true`

После выполнения миграции ошибка должна исчезнуть.
