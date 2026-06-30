# Сводка по реализации системы ролей и прав доступа

## ✅ Выполненные задачи

### 1. Установка и настройка
- ✅ Обновлена модель `User` с трейтом `HasRoles` из Spatie Laravel Permission
- ✅ Создана документация по установке (`ROLES_PERMISSIONS_SETUP.md`)

### 2. Policy классы
Созданы Policy классы для всех ресурсов:
- ✅ `UserPolicy`
- ✅ `CategoryPolicy`
- ✅ `ProductPolicy`
- ✅ `OrderPolicy`
- ✅ `ReviewPolicy`
- ✅ `StorePolicy`
- ✅ `ArticlePolicy`
- ✅ `SliderPolicy`
- ✅ `ProductCollectionPolicy`
- ✅ `AttributePolicy`
- ✅ `PaymentMethodPolicy`
- ✅ `ShippingLocationPolicy`
- ✅ `CarrierPolicy`
- ✅ `DeliveryHandlingTypePolicy`
- ✅ `InteriorIdeaPolicy`
- ✅ `NewsletterSubscriberPolicy`
- ✅ `RolePolicy`
- ✅ `PermissionPolicy`

### 3. Обновление ресурсов Filament
Все ресурсы обновлены с указанием Policy классов:
- ✅ `UserResource`
- ✅ `CategoryResource`
- ✅ `ProductResource`
- ✅ `OrderResource`
- ✅ `ReviewResource`
- ✅ `StoreResource`
- ✅ `ArticleResource`
- ✅ `SliderResource`
- ✅ `ProductCollectionResource`
- ✅ `AttributeResource`
- ✅ `PaymentMethodResource`
- ✅ `ShippingLocationResource`
- ✅ `CarrierResource`
- ✅ `DeliveryHandlingTypeResource`
- ✅ `InteriorIdeaResource`
- ✅ `NewsletterSubscriberResource`

### 4. Ресурсы для управления ролями и разрешениями
- ✅ `RoleResource` - управление ролями
  - Создание, редактирование, удаление ролей
  - Назначение разрешений ролям
  - Отображение количества разрешений и пользователей
  
- ✅ `PermissionResource` - управление разрешениями
  - Просмотр всех разрешений
  - Отображение количества ролей, использующих разрешение

### 5. Интеграция с UserResource
- ✅ Добавлена секция "Роли и разрешения" в форму пользователя
- ✅ Возможность назначения ролей при создании и редактировании пользователя
- ✅ Отображение ролей в таблице пользователей
- ✅ Автоматическая синхронизация ролей при сохранении

## Структура разрешений

Каждый ресурс использует стандартный набор разрешений:
- `viewAny {resource}` - просмотр списка
- `view {resource}` - просмотр записи
- `create {resource}` - создание
- `update {resource}` - редактирование
- `delete {resource}` - удаление

Примеры:
- `viewAny products`
- `create orders`
- `update users`
- `delete categories`

## Навигация в админке

Новый раздел **"Права доступа"** содержит:
1. **Роли** (`/admin_sv/roles`) - управление ролями
2. **Разрешения** (`/admin_sv/permissions`) - просмотр разрешений

## Подборки товаров (product_collections)

Если в админке нельзя менять активность подборок — выдайте права командой (безопасно для prod):

```bash
docker exec -it sv_app php artisan permissions:ensure-product-collections
```

Подробнее: `ROLES_PERMISSIONS_SETUP.md` (раздел «Подборки товаров»).

## Следующие шаги

1. Установить пакет Spatie Laravel Permission:
   ```bash
   docker exec -it sv_app composer require spatie/laravel-permission
   ```

2. Опубликовать и выполнить миграции:
   ```bash
   docker exec -it sv_app php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
   docker exec -it sv_app php artisan migrate
   ```

3. Создать seeder для базовых ролей и разрешений (см. `ROLES_PERMISSIONS_SETUP.md`)

4. Создать первого администратора с полными правами

## Важные замечания

- Все Policy классы проверяют разрешения через `$user->can('permission_name')`
- Система автоматически скрывает недоступные разделы из навигации
- При отсутствии прав пользователь не сможет выполнить действие
- Роли назначаются через интерфейс Filament в разделе редактирования пользователя
