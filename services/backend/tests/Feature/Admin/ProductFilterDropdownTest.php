<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductFilterDropdownTest extends TestCase
{
    public function test_filter_selects_use_fixed_positioning_inside_scrollable_panel(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));

        $page = Livewire::test(ListProducts::class)->assertSuccessful();
        $table = $page->instance()->getTable();
        $this->assertSame('75vh', $table->getFiltersFormMaxHeight());

        foreach ($table->getFiltersFormSchema() as $group) {
            $this->assertStringContainsString(
                'fi-fixed-positioning-context',
                $group->getExtraAttributes()['class'] ?? '',
            );
        }
    }
}
