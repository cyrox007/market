<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\QuickActionsWidget;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuickActionsWidgetTest extends TestCase
{
    public function test_dashboard_actions_do_not_link_to_the_removed_order_creation_page(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));

        Livewire::test(QuickActionsWidget::class)->assertSuccessful()
            ->assertSee('Все заказы')->assertDontSee('Создать заказ');
    }
}
