# Настройка системы ролей и прав доступа

## Установка

1. Установите пакет Spatie Laravel Permission:
```bash
docker exec -it sv_app composer require spatie/laravel-permission
```

2. Опубликуйте миграции:
```bash
docker exec -it sv_app php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

3. Выполните миграции:
```bash
docker exec -it sv_app php artisan migrate
```

## Создание базовых ролей и разрешений

Запуск сидера (локально и при первичной настройке):

```bash
docker exec -it sv_app php artisan db:seed --class=RolesAndPermissionsSeeder
```

Сидер: `database/seeders/RolesAndPermissionsSeeder.php` — создаёт разрешения и роли `super_admin`, `admin`, `manager`, `editor`, `viewer`.

### Подборки товаров (`product_collections`)

Разрешения: `viewAny product_collections`, `view product_collections`, `create product_collections`, `update product_collections`, `delete product_collections`.

В сидере они выданы ролям **super_admin** (все), **admin** (все, кроме управления ролями), **manager** (в т.ч. редактирование подборок в `/admin_sv/products/product-collections`).

Если на **продакшене** тогглы «Активна» / «Автоматическая» в списке подборок не кликаются — у роли пользователя нет `update product_collections`. Не перезапускайте полный сидер на проде: используйте команду, которая **добавляет** права, не снимая остальные:

```bash
# По умолчанию: super_admin, admin, manager
docker exec -it sv_app php artisan permissions:ensure-product-collections

# Только нужные роли
docker exec -it sv_app php artisan permissions:ensure-product-collections --roles=manager,admin
```

После выполнения **перелогиньтесь** в админке (кэш Spatie Permission сбрасывается командой автоматически).

Команда: `app/Console/Commands/EnsureProductCollectionPermissionsCommand.php`.

## Структура разрешений

Система использует следующие разрешения для каждого ресурса:

- `viewAny {resource}` - просмотр списка
- `view {resource}` - просмотр отдельной записи
- `create {resource}` - создание
- `update {resource}` - редактирование
- `delete {resource}` - удаление

Примеры:
- `viewAny products` - просмотр списка товаров
- `create products` - создание товаров
- `update orders` - редактирование заказов
- `delete users` - удаление пользователей

## Базовые роли

Рекомендуется создать следующие роли:

1. **Super Admin** - полный доступ ко всему
2. **Admin** - администратор с ограниченными правами
3. **Manager** - менеджер (управление заказами, товарами)
4. **Editor** - редактор (управление контентом)
5. **Viewer** - только просмотр

## Использование в коде

### Проверка разрешений в Policy

Все Policy классы используют стандартные методы проверки:
```php
public function viewAny(User $user): bool
{
    return $user->can('viewAny products');
}
```

### Назначение ролей пользователю

В админке Filament:
1. Перейдите в раздел "Пользователи"
2. Откройте пользователя для редактирования
3. В разделе "Роли и разрешения" выберите нужные роли

### Программное назначение ролей

```php
$user = User::find(1);
$user->assignRole('admin');

// Или несколько ролей
$user->assignRole(['admin', 'manager']);
```

### Проверка ролей и разрешений

```php
// Проверка роли
$user->hasRole('admin');

// Проверка разрешения
$user->can('view products');

// Проверка через роль
$user->hasPermissionTo('create orders');
```

## Ресурсы Filament

В админке доступны следующие разделы:

- **Роли** (`/admin_sv/roles`) - управление ролями
- **Разрешения** (`/admin_sv/permissions`) - управление разрешениями
- **Пользователи** (`/admin_sv/users`) - управление пользователями с назначением ролей

## Важные замечания

1. Первый пользователь должен быть создан через seeder с ролью Super Admin
2. Все Policy классы созданы и подключены к ресурсам
3. Система автоматически проверяет права доступа при работе с ресурсами
4. Если у пользователя нет прав, он не увидит соответствующие разделы в меню

## Создание первого администратора

После установки создайте первого администратора:

```php
use App\Models\User;
use Spatie\Permission\Models\Role;

$user = User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('password'),
]);

// Создайте роль Super Admin если её нет
$superAdmin = Role::firstOrCreate(['name' => 'super_admin']);

// Назначьте все разрешения роли Super Admin
$permissions = \Spatie\Permission\Models\Permission::all();
$superAdmin->syncPermissions($permissions);

// Назначьте роль пользователю
$user->assignRole('super_admin');
```
