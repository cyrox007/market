<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create 
                            {email : Email админа}
                            {--name=Admin : Имя админа}
                            {--password= : Пароль (если не указан, будет сгенерирован)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создать администратора с ролью super_admin и всеми правами';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $name = $this->option('name');
        $password = $this->option('password');

        // Проверяем, существует ли пользователь
        $user = User::where('email', $email)->first();

        if ($user) {
            $this->info("Пользователь с email {$email} уже существует.");
            
            if (!$this->confirm('Назначить роль super_admin существующему пользователю?')) {
                return 0;
            }
            
            if ($password) {
                $user->password = Hash::make($password);
                $user->save();
                $this->info('Пароль обновлен.');
            }
        } else {
            // Генерируем пароль, если не указан
            if (!$password) {
                $password = $this->generatePassword();
                $this->warn("Пароль не указан. Сгенерирован пароль: {$password}");
            }

            // Создаем пользователя
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $this->info("Пользователь {$email} создан.");
        }

        // Проверяем, существует ли роль super_admin
        $role = Role::where('name', 'super_admin')->first();

        if (!$role) {
            $this->error("Роль 'super_admin' не найдена!");
            $this->info("Запустите: php artisan db:seed --class=RolesAndPermissionsSeeder");
            return 1;
        }

        // Назначаем роль
        $user->syncRoles([$role]);

        $this->info("✅ Роль 'super_admin' успешно назначена пользователю {$user->email}.");
        $this->info("Пользователь теперь имеет полный доступ ко всем ресурсам.");

        if (!$user->wasRecentlyCreated && !$password) {
            $this->newLine();
            $this->warn("Пароль не был изменен. Используйте существующий пароль.");
        }

        return 0;
    }

    /**
     * Генерирует случайный пароль
     */
    private function generatePassword(): string
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 12);
    }
}
