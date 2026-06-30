# Инструкция по завершению настройки SEO

## Шаги для завершения настройки

### 1. Установка пакетов
```bash
cd services/backend
composer install
```

### 2. Публикация миграций и конфигурации
```bash
php artisan vendor:publish --tag="seo-migrations"
php artisan vendor:publish --tag="seo-config"
```

### 3. Запуск миграций
```bash
php artisan migrate
```

### 4. Настройка переменных окружения
Добавьте в ваш `.env` файл:
```
SEO_SITE_DOMAIN=https://your-domain.com
```

Если переменная не указана, будет использоваться значение из `APP_URL`.

### 5. Использование в контроллерах/ресурсах

Для получения SEO данных используйте:

```php
// В контроллере или ресурсе
$seo = $product->seo()->exists()
    ? $product->seo
    : $product->getDynamicSEOData();
```

Или используйте встроенные методы пакета:
```php
// Пакет автоматически использует getDynamicSEOData() если SEO данные не сохранены
$seo = $product->seo;
```

## Что было сделано

1. ✅ Добавлены пакеты `ralphjsmit/laravel-filament-seo` и `ralphjsmit/laravel-seo` в `composer.json`
2. ✅ Создан трейт `MetaUniversalSEO` в `app/Models/Traits/SEO/MetaUniversalSEO.php`
3. ✅ Трейты `HasSEO` и `MetaUniversalSEO` добавлены к моделям `Product` и `Category`
4. ✅ Обновлены Filament формы:
   - `ProductForm` - заменена секция SEO на компонент из пакета
   - `CategoryForm` - добавлена секция SEO с компонентом из пакета

## Особенности реализации

- Трейт `MetaUniversalSEO` автоматически генерирует SEO данные на основе полей модели
- Использует Spatie Media Library для получения изображений
- Поддерживает автоматическую генерацию canonical URL через `getFullPathAttribute()`
- Если SEO данные сохранены в базе через Filament, они имеют приоритет над динамически сгенерированными

