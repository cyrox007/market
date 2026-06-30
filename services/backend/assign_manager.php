<?php

/**
 * Скрипт для назначения роли manager пользователю
 *
 * Использование:
 * docker exec sv_app php assign_manager.php <user_email>
 *
 * Или для создания нового менеджера:
 * docker exec sv_app php assign_manager.php <user_email> <user_name> <password>
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

if ($argc < 2) {
    echo "Использование:\n";
    echo "  php assign_manager.php <user_email> [user_name] [password]\n";
    echo "\n";
    echo "Примеры:\n";
    echo "  php assign_manager.php manager@example.com\n";
    echo "  php assign_manager.php manager@example.com Менеджер Иванов password123\n";
    exit(1);
}

$email = $argv[1];
$name = $argv[2] ?? 'Manager';
$password = $argv[3] ?? null;

// Получаем или создаем пользователя
$user = User::where('email', $email)->first();

if (!$user) {
    if (!$password) {
        echo "Ошибка: Пользователь с email {$email} не найден. Укажите пароль для создания нового пользователя.\n";
        exit(1);
    }

    $user = User::create([
        'name' => $name,
        'email' => $email,
        'password' => Hash::make($password),
    ]);

    echo "Пользователь {$email} создан.\n";
} else {
    echo "Найден пользователь: {$user->name} ({$user->email})\n";

    if ($password) {
        $user->password = Hash::make($password);
        $user->save();
        echo "Пароль обновлен.\n";
    }
}

// Получаем роль manager
$role = Role::where('name', 'manager')->first();

if (!$role) {
    echo "Ошибка: Роль 'manager' не найдена. Запустите seeder: php artisan db:seed --class=RolesAndPermissionsSeeder\n";
    exit(1);
}

// Назначаем роль
$user->syncRoles([$role]);

echo "Роль 'manager' успешно назначена пользователю {$user->email}.\n";
echo "Менеджер имеет доступ к заказам, товарам, категориям, отзывам, блокам и почтовым событиям.\n";
