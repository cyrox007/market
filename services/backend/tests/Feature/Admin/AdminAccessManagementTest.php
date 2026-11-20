<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Support\AdminAccessCatalog;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessManagementTest extends TestCase
{
    private function operator(): User
    {
        $role = Role::findOrCreate('super_admin', 'web');
        foreach (['viewAny roles', 'view roles', 'create roles', 'update roles'] as $key) {
            $role->givePermissionTo(Permission::findOrCreate($key, 'web'));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));

        return $user;
    }

    public function test_retired_product_blocks_permissions_are_hidden_but_persisted_for_audit(): void
    {
        $active = Permission::findOrCreate('view products', 'web');
        $oldFeature = Permission::findOrCreate('view product_feature_blocks', 'web');
        $oldDelivery = Permission::findOrCreate('create product_delivery_blocks', 'web');
        $role = Role::findOrCreate('legacy_block_role', 'web');
        $role->syncPermissions([$active, $oldFeature, $oldDelivery]);

        $visible = AdminAccessCatalog::withoutRetiredPermissions(Permission::query())
            ->pluck('name')
            ->all();
        $this->assertContains('view products', $visible);
        $this->assertNotContains('view product_feature_blocks', $visible);
        $this->assertNotContains('create product_delivery_blocks', $visible);
        $this->assertEqualsCanonicalizing(
            [(string) $active->id],
            AdminAccessCatalog::selectedGroups($role->fresh()->load('permissions'))['products']
        );
        $this->assertCount(3, $role->fresh()->permissions);
    }

    public function test_dictionary_has_russian_descriptions_and_keeps_unknown_keys(): void
    {
        $this->assertSame('Каталог и товары', AdminAccessCatalog::domainLabel('create attributes'));
        $this->assertSame('Характеристики и цвета', AdminAccessCatalog::resourceLabel('create attributes'));
        $this->assertSame('Создавать', AdminAccessCatalog::actionLabel('create attributes'));
        $this->assertSame('Просматривать список', AdminAccessCatalog::actionLabel('viewAny orders'));
        $this->assertSame('Системные операции', AdminAccessCatalog::domainLabel('delete_all_catalog'));
        $this->assertSame('Другие разрешения', AdminAccessCatalog::domainLabel('approve custom_module'));
        $this->assertSame('Менеджер', AdminAccessCatalog::roleTitle('manager'));
        $this->assertSame([8, 4], AdminAccessCatalog::selectedIds([
            'products' => ['8', 'invalid'],
            'categories' => ['4', '8'],
        ]));
    }

    public function test_new_role_saves_selected_web_permissions_from_multiple_panels(): void
    {
        $this->operator();
        $products = Permission::findOrCreate('create products', 'web');
        $orders = Permission::findOrCreate('view orders', 'web');
        $extra = Permission::findOrCreate('approve custom_module', 'web');

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'regional_operator',
                'permission_groups' => [
                    'products' => [(string) $products->id],
                    'orders' => [(string) $orders->id],
                    'custom_module' => [(string) $extra->id],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $saved = Role::where('name', 'regional_operator')->firstOrFail();
        $this->assertSame('web', $saved->guard_name);
        $this->assertEqualsCanonicalizing(
            ['create products', 'view orders', 'approve custom_module'],
            $saved->permissions->pluck('name')->all()
        );
    }

    public function test_edit_role_prefills_existing_permissions_and_syncs_without_leaving_old_grants(): void
    {
        $this->operator();
        $view = Permission::findOrCreate('view products', 'web');
        $delete = Permission::findOrCreate('delete products', 'web');
        $custom = Permission::findOrCreate('approve custom_module', 'web');
        $role = Role::findOrCreate('catalog_assistant', 'web');
        $role->syncPermissions([$view, $custom]);

        $initial = AdminAccessCatalog::selectedGroups($role->fresh()->load('permissions'));
        $this->assertContains((string) $view->id, $initial['products']);
        $this->assertContains((string) $custom->id, $initial['custom_module']);

        Livewire::test(EditRole::class, ['record' => $role->id])
            ->fillForm([
                'permission_groups' => [
                    'products' => [(string) $delete->id],
                    'custom_module' => [(string) $custom->id],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            ['delete products', 'approve custom_module'],
            $role->fresh()->permissions->pluck('name')->all()
        );
    }

    public function test_edit_role_can_revoke_every_permission(): void
    {
        $this->operator();
        $permission = Permission::findOrCreate('create products', 'web');
        $role = Role::findOrCreate('empty_role_test', 'web');
        $role->syncPermissions([$permission]);

        Livewire::test(EditRole::class, ['record' => $role->id])
            ->fillForm(['permission_groups' => ['products' => []]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount(0, $role->fresh()->permissions);
    }
}
