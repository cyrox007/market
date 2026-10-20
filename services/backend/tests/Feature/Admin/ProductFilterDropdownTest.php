<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Enums\FiltersLayout;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductFilterDropdownTest extends TestCase
{
    public function test_filters_use_modal_without_fixed_positioning_override(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));

        $page = Livewire::test(ListProducts::class)->assertSuccessful();
        $table = $page->instance()->getTable();
        $this->assertSame(FiltersLayout::Modal, $table->getFiltersLayout());

        foreach ($table->getFiltersFormSchema() as $group) {
            $this->assertStringNotContainsString(
                'fi-fixed-positioning-context',
                $group->getExtraAttributes()['class'] ?? '',
            );
        }
    }
}
