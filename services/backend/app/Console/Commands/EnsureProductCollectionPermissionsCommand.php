<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Выдать права на подборки товаров без полного пересоздания ролей (безопасно для prod).
 */
class EnsureProductCollectionPermissionsCommand extends Command
{
    protected $signature = 'permissions:ensure-product-collections
                            {--roles=super_admin,admin,manager : Роли через запятую}';

    protected $description = 'Выдать права view/create/update/delete product_collections указанным ролям';

    public function handle(): int
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionNames = [];
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $action) {
            $name = "{$action} product_collections";
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $permissionNames[] = $name;
        }

        $roles = array_map('trim', explode(',', (string) $this->option('roles')));

        foreach ($roles as $roleName) {
            $role = Role::findByName($roleName, 'web');
            if (!$role) {
                $this->warn("Роль не найдена: {$roleName}");

                continue;
            }
            $role->givePermissionTo($permissionNames);
            $this->info("Права product_collections выданы роли: {$roleName}");
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->info('Готово. Перелогиньтесь в админке, если тогглы всё ещё заблокированы.');

        return self::SUCCESS;
    }
}
