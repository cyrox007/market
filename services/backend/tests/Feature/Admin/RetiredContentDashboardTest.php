<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\ContentStatsOverviewWidget;
use App\Filament\Widgets\DashboardRoleChecks;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RetiredContentDashboardTest extends TestCase
{
    public function test_content_widget_omits_archived_ideas_and_moved_stores(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));

        Livewire::test(ContentStatsOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Статей')
            ->assertSee('Слайдеров')
            ->assertSee('Подписчиков рассылки')
            ->assertDontSee('Идей интерьера')
            ->assertDontSee('Магазинов');
    }

    public function test_legacy_interior_ideas_permission_alone_does_not_enable_content_dashboard(): void
    {
        Permission::findOrCreate('viewAny interior_ideas', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('viewAny interior_ideas');
        $this->actingAs($user);

        $this->assertFalse(DashboardRoleChecks::canSeeContent());
    }

    public function test_live_newsletter_permission_enables_content_dashboard(): void
    {
        Permission::findOrCreate('viewAny newsletter_subscribers', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('viewAny newsletter_subscribers');
        $this->actingAs($user);

        $this->assertTrue(DashboardRoleChecks::canSeeContent());
    }
}
