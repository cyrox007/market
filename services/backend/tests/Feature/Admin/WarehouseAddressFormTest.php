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
    public function test_selecting_building_clears_previous_required_error_only_for_that_field(): void
    {
        Http::fake(['*' => Http::response([])]);
        $form = Livewire::test(CreateWarehouse::class)
            ->fillForm(['name' => 'Test', 'source_type' => 'physical', 'stock_mode' => 'quantity', 'directory_locality' => 'city'])
            ->call('create')
            ->assertHasFormErrors(['address_external_id' => 'required']);
        $form->set('data.address_external_id', '123')
            ->assertHasNoFormErrors(['address_external_id']);
    }

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
            'processing_days_min' => 1, 'processing_days_max' => 3,
        ])->assertSee('Основное')->assertSee('Адрес')->assertSee('Доставка')
            ->call('create')->assertHasNoFormErrors();
        $warehouse = Warehouse::where('name', 'Воронежский склад')->firstOrFail();
        $this->assertSame('123', $warehouse->address_external_id);
        $this->assertSame('Воронеж, ДОМ 10', $warehouse->address);
        $this->assertSame('warehouse_point', $warehouse->coordinate_precision);
        $this->assertSame(1, $warehouse->processing_days_min);
        $this->assertSame(3, $warehouse->processing_days_max);
    }

    public function test_saved_address_hydrates_without_hierarchy_request(): void
    {
        Http::fake(['*/directory/*' => Http::response([])]);
        $warehouse = Warehouse::create([
            'name' => 'Склад', 'address_external_id' => '123', 'address' => 'Воронеж, ДОМ 10',
            'address_snapshot' => ['selectionId' => '123::10', 'path' => [
                ['kind' => 'region', 'externalId' => 'region', 'label' => 'Воронежская область'],
                ['kind' => 'locality', 'externalId' => 'city', 'label' => 'Воронеж'],
                ['kind' => 'street', 'externalId' => 'street', 'label' => 'Московский'],
            ]],
        ]);
        Livewire::test(EditWarehouse::class, ['record' => $warehouse->getRouteKey()])
            ->assertSee('Условия доставки пока не настроены')
            ->assertFormSet(['directory_region' => 'region', 'directory_locality' => 'city', 'directory_street' => 'street', 'address_external_id' => '123::10']);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/hierarchy/'));
    }

    public function test_operator_can_create_a_directory_delivery_profile(): void
    {
        Http::fake(['*/address/regions*' => Http::response([
            ['externalId' => 'region48', 'name' => 'Липецкая область', 'label' => 'Липецкая область'],
        ]), '*/address/localities*' => Http::response([
            ['externalId' => 'city48', 'name' => 'Липецк', 'label' => 'Липецкая область, г. Липецк'],
        ]), '*' => Http::response([
            'target' => ['kind' => 'region', 'externalId' => 'region48', 'name' => 'Липецкая область'],
            'path' => [], 'label' => 'Липецкая область',
        ])]);
        $warehouse = Warehouse::create(['name' => 'Склад']);
        Livewire::test(\App\Filament\Resources\Shipping\Warehouses\RelationManagers\DeliveryProfilesRelationManager::class, [
            'ownerRecord' => $warehouse, 'pageClass' => EditWarehouse::class,
        ])->callTableAction('create', data: [
            'name' => 'Липецкое направление', 'coverage_type' => 'locations',
            'directory_territories' => [['region_id' => 'region48', 'locality_id' => null]],
            'base_price' => 1500, 'delivery_days_min' => 2, 'delivery_days_max' => 4,
            'priority' => 100, 'is_active' => true, 'price_per_km' => 0,
        ])->assertHasNoTableActionErrors();
        $profile = $warehouse->deliveryProfiles()->firstOrFail();
        $this->assertSame('region48', $profile->directory_territories[0]['externalId']);
        $this->assertSame('1500.00', $profile->base_price);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/address/localities')
            && $request['parentExternalId'] === 'region48');
    }
}
