<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Widgets\AdminUserPresenceWidget;
use App\Models\Product\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserPresenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
    }

    private function staff(string $name, string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole($role);

        return $user;
    }

    private function heartbeat(string $window, array $page): array
    {
        return [
            'actor_id' => (string) auth('web')->id(),
            'window_id' => $window,
            'targets' => [],
            'editing' => [],
            'page' => $page,
        ];
    }

    public function test_only_administrators_can_view_staff_activity_widget(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs($this->staff($role, $role), 'web');
            $this->assertTrue(AdminUserPresenceWidget::canView());
        }

        foreach (['manager', 'editor', 'viewer'] as $role) {
            $this->actingAs($this->staff($role, $role), 'web');
            $this->assertFalse(AdminUserPresenceWidget::canView());
        }
    }

    public function test_dashboard_reports_the_latest_tab_per_person_and_keeps_tabs_separate(): void
    {
        $anna = $this->staff('Анна', 'manager');
        $admin = $this->staff('Администратор', 'admin');

        $this->actingAs($anna, 'web');
        $first = (string) Str::uuid();
        $second = (string) Str::uuid();
        $page = ['kind' => 'dashboard'];

        $this->postJson('/admin_sv/editing-presence', $this->heartbeat($first, $page))->assertOk();
        $this->travel(1)->seconds();
        $this->postJson('/admin_sv/editing-presence', $this->heartbeat($second, $page))->assertOk();
        $this->assertDatabaseCount('admin_panel_presence', 2);

        $this->actingAs($admin, 'web');
        Livewire::test(AdminUserPresenceWidget::class)
            ->assertSuccessful()
            ->assertSee('Анна')
            ->assertSee('Главная панель')
            ->assertSee('2 активные вкладки');

        $this->actingAs($anna, 'web');
        $this->deleteJson('/admin_sv/editing-presence', [
            'actor_id' => (string) $anna->id,
            'window_id' => $second,
        ])->assertOk();
        $this->assertDatabaseCount('admin_panel_presence', 1);
        $this->assertDatabaseHas('admin_panel_presence', ['user_id' => $anna->id, 'window_id' => $first]);
    }

    public function test_resource_page_requires_permission_and_rejects_unsafe_slugs(): void
    {
        $viewer = $this->staff('Редактор', 'editor');
        $this->actingAs($viewer, 'web');

        $window = (string) Str::uuid();
        $validPage = [
            'kind' => 'resource',
            'resource' => ProductResource::getSlug(),
            'action' => 'index',
        ];
        $this->postJson('/admin_sv/editing-presence', $this->heartbeat($window, $validPage))
            ->assertForbidden();
        $this->assertDatabaseCount('admin_panel_presence', 0);

        Permission::findOrCreate('viewAny products', 'web');
        $viewer->givePermissionTo('viewAny products');
        $this->postJson('/admin_sv/editing-presence', $this->heartbeat($window, $validPage))->assertOk();
        $this->assertDatabaseHas('admin_panel_presence', [
            'user_id' => $viewer->id,
            'resource_slug' => ProductResource::getSlug(),
            'page_action' => 'index',
        ]);

        $invalid = ['kind' => 'resource', 'resource' => 'App\\Models\\User', 'action' => 'index'];
        $this->postJson('/admin_sv/editing-presence', $this->heartbeat($window, $invalid))
            ->assertUnprocessable();
        $this->assertDatabaseCount('admin_panel_presence', 1);
    }

    public function test_record_editing_is_not_reported_without_edit_permission(): void
    {
        $user = $this->staff('Оператор', 'manager');
        $this->actingAs($user, 'web');
        foreach (['viewAny products', 'view products'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $product = Product::factory()->create();
        $page = [
            'kind' => 'resource',
            'resource' => ProductResource::getSlug(),
            'action' => 'edit',
            'record' => (string) $product->id,
        ];

        $this->postJson('/admin_sv/editing-presence', $this->heartbeat((string) Str::uuid(), $page))
            ->assertForbidden();
        $this->assertDatabaseCount('admin_panel_presence', 0);

        $user->givePermissionTo(Permission::findOrCreate('update products', 'web'));
        $this->postJson('/admin_sv/editing-presence', $this->heartbeat((string) Str::uuid(), $page))
            ->assertOk();
        $this->assertDatabaseHas('admin_panel_presence', [
            'user_id' => $user->id,
            'page_action' => 'edit',
            'record_key' => (string) $product->id,
        ]);
    }

    public function test_stale_activity_disappears_without_durable_history(): void
    {
        $staff = $this->staff('Сотрудник', 'manager');
        $admin = $this->staff('Руководитель', 'super_admin');
        $this->actingAs($staff, 'web');
        $window = (string) Str::uuid();
        $this->postJson('/admin_sv/editing-presence', $this->heartbeat($window, ['kind' => 'dashboard']))
            ->assertOk();

        $this->travel(91)->seconds();
        $this->actingAs($admin, 'web');
        Livewire::test(AdminUserPresenceWidget::class)
            ->assertSuccessful()
            ->assertDontSee('Сотрудник')
            ->assertSee('Сейчас нет сотрудников');

        // Any next heartbeat purges expired rows rather than retaining a history.
        $this->postJson('/admin_sv/editing-presence', $this->heartbeat((string) Str::uuid(), ['kind' => 'dashboard']))
            ->assertOk();
        $this->assertDatabaseMissing('admin_panel_presence', ['window_id' => $window]);
    }

    public function test_other_users_cannot_release_another_staff_members_tab(): void
    {
        $one = $this->staff('Первый', 'manager');
        $two = $this->staff('Второй', 'manager');
        $window = (string) Str::uuid();
        $this->actingAs($one, 'web');
        $this->postJson('/admin_sv/editing-presence', $this->heartbeat($window, ['kind' => 'dashboard']))
            ->assertOk();

        $this->actingAs($two, 'web');
        $this->deleteJson('/admin_sv/editing-presence', [
            'actor_id' => (string) $two->id,
            'window_id' => $window,
        ])->assertOk();

        $this->assertDatabaseHas('admin_panel_presence', ['user_id' => $one->id, 'window_id' => $window]);
        $this->postJson('/admin_sv/editing-presence', [
            ...$this->heartbeat($window, ['kind' => 'dashboard']),
            'actor_id' => (string) $one->id,
        ])->assertStatus(409);
        $this->assertDatabaseMissing('admin_panel_presence', ['user_id' => $two->id]);
    }
    public function test_record_context_is_hidden_from_a_dashboard_viewer_without_resource_access(): void
    {
        $actor = $this->staff('Оператор карточки', 'manager');
        foreach (['viewAny products', 'view products', 'update products'] as $permission) {
            $actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $product = Product::factory()->create();
        $this->actingAs($actor, 'web');
        $page = [
            'kind' => 'resource',
            'resource' => ProductResource::getSlug(),
            'action' => 'edit',
            'record' => (string) $product->id,
        ];
        $this->postJson('/admin_sv/editing-presence', $this->heartbeat((string) Str::uuid(), $page))
            ->assertOk();

        $admin = $this->staff('Администратор', 'admin');
        $this->actingAs($admin, 'web');
        Livewire::test(AdminUserPresenceWidget::class)
            ->assertSuccessful()
            ->assertSee('Оператор карточки')
            ->assertSee('Раздел с ограниченным доступом')
            ->assertDontSee('Редактирует запись #'.$product->id);

        foreach (['viewAny products', 'view products'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        Livewire::test(AdminUserPresenceWidget::class)
            ->assertSuccessful()
            ->assertSee('Редактирует запись #'.$product->id)
            ->assertDontSee('Раздел с ограниченным доступом');
    }

}
