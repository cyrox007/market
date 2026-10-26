<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Stores\Pages\CreateStore;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreDirectoryFormTest extends TestCase
{
    public function test_store_accepts_formatted_eight_hundred_phone(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        Http::fake(fn () => Http::response([]));
        $site = \App\Models\Inventory\PhysicalSite::create(['name' => 'Site', 'address' => 'Address', 'city' => 'City']);
        Livewire::test(CreateStore::class)->fillForm(['name' => 'Phone shop', 'slug' => 'phone-shop',
            'physical_site_id' => $site->id, 'store_stock_mode' => 'showroom', 'phone' => '8 (800) 222-85-86'])
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('stores', ['slug' => 'phone-shop', 'phone' => '+78002228586']);
    }

    public function test_address_dialog_uses_hierarchy_and_map_instead_of_numeric_coordinate_fields(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        Http::fake(fn () => Http::response([]));
        Livewire::test(CreateStore::class)
            ->mountAction(TestAction::make('createOption')->schemaComponent('store_address', 'form'))
            ->assertActionMounted(TestAction::make('createOption')->schemaComponent('store_address', 'form'))
            ->assertFormFieldExists('directory_region')
            ->assertFormFieldExists('directory_locality')
            ->assertFormFieldExists('directory_street')
            ->assertFormFieldExists('address_external_id')
            ->assertFormFieldExists('_warehouse_map')
            ->assertFormFieldExists('latitude', fn ($field) => $field instanceof \Filament\Forms\Components\Hidden)
            ->assertFormFieldExists('longitude', fn ($field) => $field instanceof \Filament\Forms\Components\Hidden);
    }
}
