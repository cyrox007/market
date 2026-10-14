<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Shipping\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Stores\Pages\CreateStore;
use App\Models\Inventory\PhysicalSite;
use App\Models\Inventory\Warehouse;
use App\Models\Page\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreWarehouseSetupFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        Http::preventStrayRequests();
    }

    private function site(): PhysicalSite
    {
        return PhysicalSite::create(['name' => 'Головной офис', 'address' => 'Липецк, д. 10', 'city' => 'Липецк',
            'address_external_id' => '123', 'address_snapshot' => ['selectionId' => '123::10', 'path' => [['kind' => 'locality', 'name' => 'Липецк']]],
            'latitude' => 52.6, 'longitude' => 39.6]);
    }

    public function test_operator_can_create_shop_using_existing_warehouse_and_shared_site(): void
    {
        $site = $this->site();
        $warehouse = Warehouse::create(['name' => 'Склад при магазине', 'physical_site_id' => $site->id]);
        Livewire::test(CreateStore::class)->fillForm([
            'name' => 'Магазин', 'slug' => 'shop', 'phone' => '+79000000000', 'store_stock_mode' => 'warehouse',
            'warehouse_id' => $warehouse->id, 'physical_site_id' => $site->id,
        ])->call('create')->assertHasNoFormErrors();
        $store = Store::where('slug', 'shop')->firstOrFail();
        $this->assertSame($warehouse->id, $store->warehouse_id);
        $this->assertSame($site->address, $store->address);
        $this->assertSame(1, Warehouse::count());
        Http::assertNothingSent();
    }

    public function test_operator_can_create_warehouse_at_existing_site_without_duplicate_address(): void
    {
        $site = $this->site();
        Livewire::test(CreateWarehouse::class)->fillForm([
            'name' => 'Склад', 'source_type' => 'physical', 'stock_mode' => 'quantity', 'physical_site_id' => $site->id,
        ])->call('create')->assertHasNoFormErrors();
        $warehouse = Warehouse::firstOrFail();
        $this->assertSame($site->id, $warehouse->physical_site_id);
        $this->assertSame($site->address, $warehouse->address);
        Http::assertNothingSent();
    }
}
