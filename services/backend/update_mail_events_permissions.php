<?php

/**
 * Скрипт для добавления разрешений для почтовых событий
 * Запустить: php update_mail_events_permissions.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Очищаем кеш разрешений
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

echo "Создание разрешений для mail_events...\n";

// Создаем разрешения для mail_events
$actions = ['viewAny', 'view', 'create', 'update', 'delete'];

foreach ($actions as $action) {
    $permission = Permission::firstOrCreate([
        'name' => "{$action} mail_events",
        'guard_name' => 'web',
    ]);
    echo "  ✓ Создано разрешение: {$permission->name}\n";
}

// Назначаем разрешения ролям
$superAdmin = Role::where('name', 'super_admin')->first();
$admin = Role::where('name', 'admin')->first();
$manager = Role::where('name', 'manager')->first();

if ($superAdmin) {
    // Super Admin получает все разрешения автоматически
    $superAdmin->syncPermissions(Permission::all());
    echo "  ✓ Super Admin: все разрешения назначены\n";
}

if ($admin) {
    // Admin получает все разрешения кроме roles и permissions
    $adminPermissions = Permission::whereNotIn('name', [
        'viewAny roles',
        'view roles',
        'create roles',
        'update roles',
        'delete roles',
        'viewAny permissions',
        'view permissions',
        'create permissions',
        'update permissions',
        'delete permissions',
    ])->get();
    $admin->syncPermissions($adminPermissions);
    echo "  ✓ Admin: разрешения для mail_events назначены\n";
}

if ($manager) {
    // Manager получает разрешения для mail_events
    $managerPermissions = Permission::whereIn('name', [
        'viewAny orders',
        'view orders',
        'create orders',
        'update orders',
        'viewAny products',
        'view products',
        'create products',
        'update products',
        'viewAny categories',
        'view categories',
        'create categories',
        'update categories',
        'viewAny reviews',
        'view reviews',
        'update reviews',
        'delete reviews',
        'viewAny product_feature_blocks',
        'view product_feature_blocks',
        'create product_feature_blocks',
        'update product_feature_blocks',
        'delete product_feature_blocks',
        'viewAny product_delivery_blocks',
        'view product_delivery_blocks',
        'create product_delivery_blocks',
        'update product_delivery_blocks',
        'delete product_delivery_blocks',
        'viewAny mail_events',
        'view mail_events',
        'create mail_events',
        'update mail_events',
    ])->get();
    $manager->syncPermissions($managerPermissions);
    echo "  ✓ Manager: разрешения для mail_events назначены\n";
}

// Очищаем кеш разрешений
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

echo "\nГотово! Разрешения для почтовых событий созданы и назначены.\n";
echo "Теперь перезагрузите страницу админки.\n";
