<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Shipping\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Shipping\Warehouses\Pages\EditWarehouse;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseAddressFormTest extends TestCase
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

    public function test_operator_can_save_one_kladr_building_and_warehouse_point(): void
    {
        Http::fake(['*' => Http::response([
            'target' => ['kind' => 'building', 'source' => 'kladr', 'externalId' => '123', 'name' => '1,10'],
            'path' => [['kind' => 'locality', 'externalId' => 'city', 'label' => 'Воронеж']],
            'label' => 'Воронеж, ДОМ 1,10',
        ])]);
        Livewire::test(CreateWarehouse::class)->fillForm([
            'name' => 'Воронежский склад', 'source_type' => 'physical', 'stock_mode' => 'quantity',
            'directory_locality' => 'city', 'address_external_id' => '123::10',
            'latitude' => 51.66, 'longitude' => 39.2,
        ])->call('create')->assertHasNoFormErrors();
        $warehouse = Warehouse::where('name', 'Воронежский склад')->firstOrFail();
        $this->assertSame('123', $warehouse->address_external_id);
        $this->assertSame('Воронеж, ДОМ 10', $warehouse->address);
        $this->assertSame('warehouse_point', $warehouse->coordinate_precision);
    }

    public function test_saved_address_hydrates_without_external_request(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Склад', 'address_external_id' => '123', 'address' => 'Воронеж, ДОМ 10',
            'address_snapshot' => ['selectionId' => '123::10', 'path' => [
                ['kind' => 'locality', 'externalId' => 'city', 'label' => 'Воронеж'],
                ['kind' => 'street', 'externalId' => 'street', 'label' => 'Московский'],
            ]],
        ]);
        Livewire::test(EditWarehouse::class, ['record' => $warehouse->getRouteKey()])
            ->assertFormSet(['directory_locality' => 'city', 'directory_street' => 'street', 'address_external_id' => '123::10']);
        Http::assertNothingSent();
    }
}
