<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(['viewAny', 'view', 'create', 'update', 'delete'])
            ->map(fn (string $action) => Permission::firstOrCreate([
                'name' => "{$action} warehouses",
                'guard_name' => 'web',
            ]));

        $all = $permissions->pluck('name')->all();
        $read = ['viewAny warehouses', 'view warehouses'];
        $manager = ['viewAny warehouses', 'view warehouses', 'update warehouses'];

        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            $role?->givePermissionTo($all);
        }

        $managerRole = Role::query()
            ->where('name', 'manager')
            ->where('guard_name', 'web')
            ->first();

        $managerRole?->givePermissionTo($manager);

        $viewerRole = Role::query()
            ->where('name', 'viewer')
            ->where('guard_name', 'web')
            ->first();

        $viewerRole?->givePermissionTo($read);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = [
            'viewAny warehouses',
            'view warehouses',
            'create warehouses',
            'update warehouses',
            'delete warehouses',
        ];

        foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
            foreach ($permissionNames as $permissionName) {
                if ($role->hasPermissionTo($permissionName)) {
                    $role->revokePermissionTo($permissionName);
                }
            }
        }

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $permissionNames)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
