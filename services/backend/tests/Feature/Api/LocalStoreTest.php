<?php

namespace Tests\Feature\Api;

use App\Models\Inventory\PhysicalSite;
use App\Models\Page\Store;
use App\Services\Inventory\LocalStoreResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalStoreTest extends TestCase
{
    private function store(string $id, int $priority = 0, bool $active = true): Store
    {
        $site = PhysicalSite::create(['name' => 'Site', 'city' => 'Одинаковое название', 'address' => 'Адрес магазина',
            'address_snapshot' => ['path' => [['kind' => 'locality', 'externalId' => $id, 'source' => 'gar']]]]);

        return Store::create(['name' => 'Shop', 'phone' => '123', 'address' => $site->address, 'city' => $site->city,
            'physical_site_id' => $site->id, 'priority' => $priority, 'is_active' => $active]);
    }

    public function test_standalone_store_is_selected_by_directory_id_not_city_name(): void
    {
        $this->store('other', -10);
        $shop = $this->store('selected');
        $destination = ['path' => [['kind' => 'locality', 'externalId' => 'selected', 'source' => 'gar']]];
        $selected = app(LocalStoreResolver::class)->resolve($destination);
        $this->assertSame($shop->id, $selected?->id);
        $this->assertNull($selected->warehouse_id);
        $this->assertNull(app(LocalStoreResolver::class)->resolve(['path' => []]));
    }

    public function test_current_endpoint_returns_contacts_without_requiring_stock(): void
    {
        $this->store('selected', -1, false);
        $shop = $this->store('selected');
        Http::fake(fn () => Http::response(['target' => ['kind' => 'locality', 'externalId' => 'selected', 'source' => 'gar'], 'path' => []]));
        $this->getJson('/api/v1/stores/current?directory_external_id=selected')->assertOk()
            ->assertJsonPath('store.id', $shop->id)->assertJsonPath('store.phone', '123')
            ->assertJsonPath('store.address', 'Адрес магазина')->assertJsonPath('store.has_own_stock', false)
            ->assertJsonPath('store.pickup_enabled', false);
        $this->getJson('/api/v1/stores/current')->assertOk()->assertJsonPath('store', null);
    }

    public function test_linked_store_and_standalone_store_use_same_locality_selection(): void
    {
        $shop = $this->store('selected');
        $warehouse = \App\Models\Inventory\Warehouse::create([
            'name' => 'Warehouse', 'physical_site_id' => $shop->physical_site_id,
            'source_type' => 'physical', 'stock_mode' => 'quantity', 'is_active' => true,
        ]);
        $shop->update(['warehouse_id' => $warehouse->id]);
        $selected = app(LocalStoreResolver::class)->resolve([
            'path' => [['kind' => 'locality', 'externalId' => 'selected', 'source' => 'gar']],
        ]);
        $this->assertSame($shop->id, $selected?->id);
        $this->assertSame($warehouse->id, $selected?->warehouse_id);
        $payload = (new \App\Http\Resources\StoreResource($selected))->resolve();
        $this->assertSame('selected', $payload['locality']['externalId']);
        $this->assertTrue($payload['has_own_stock']);
    }
}
