<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Очищаем кеш разрешений
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Создаем все разрешения для ресурсов
        $resources = [
            'users',
            'categories',
            'products',
            'orders',
            'reviews',
            'stores',
            'articles',
            'sliders',
            'product_collections',
            'attributes',
            'payment_methods',
            'shipping_locations',
            'carriers',
            'delivery_handling_types',
            'interior_ideas',
            'newsletter_subscribers',
            'product_feature_blocks',
            'product_delivery_blocks',
            'roles',
            'permissions',
            'mail_events',
            'catalog_sync',
        ];

        $actions = ['viewAny', 'view', 'create', 'update', 'delete'];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$action} {$resource}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // Временное разрешение: полная очистка каталога (товары + категории)
        Permission::firstOrCreate([
            'name' => 'delete_all_catalog',
            'guard_name' => 'web',
        ]);

        // Сервисная страница: очереди, кэш, очистка каталога
        Permission::firstOrCreate([
            'name' => 'viewAny service_tools',
            'guard_name' => 'web',
        ]);

        // Создаем роли
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        // Super Admin - все разрешения (включая delete_all_catalog)
        $superAdmin->syncPermissions(Permission::all());

        // Admin - все кроме управления ролями и разрешениями
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

        // Manager - управление заказами, товарами, категориями, характеристиками, блоками, почтовыми событиями
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
            'viewAny attributes',
            'view attributes',
            'create attributes',
            'update attributes',
            'delete attributes',
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
            'viewAny catalog_sync',
            'viewAny product_collections',
            'view product_collections',
            'create product_collections',
            'update product_collections',
            'delete product_collections',
        ])->get();
        $manager->syncPermissions($managerPermissions);

        // Editor - управление контентом
        $editorPermissions = Permission::whereIn('name', [
            'viewAny articles',
            'view articles',
            'create articles',
            'update articles',
            'delete articles',
            'viewAny sliders',
            'view sliders',
            'create sliders',
            'update sliders',
            'delete sliders',
            'viewAny stores',
            'view stores',
            'create stores',
            'update stores',
            'delete stores',
            'viewAny interior_ideas',
            'view interior_ideas',
            'create interior_ideas',
            'update interior_ideas',
            'delete interior_ideas',
            'viewAny newsletter_subscribers',
            'view newsletter_subscribers',
        ])->get();
        $editor->syncPermissions($editorPermissions);

        // Viewer - только просмотр
        $viewerPermissions = Permission::where('name', 'like', 'viewAny%')
            ->orWhere('name', 'like', 'view %')
            ->get();
        $viewer->syncPermissions($viewerPermissions);

        $this->command->info('Роли и разрешения успешно созданы!');
        $this->command->info('Создано ролей: ' . Role::count());
        $this->command->info('Создано разрешений: ' . Permission::count());
    }
}
