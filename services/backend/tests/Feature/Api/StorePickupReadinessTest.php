<?php

namespace Tests\Feature\Api;

use App\Http\Resources\StoreResource;
use App\Models\Inventory\PhysicalSite;
use App\Models\Inventory\Warehouse;
use App\Models\Page\Store;
use Illuminate\Http\Request;
use Tests\TestCase;

class StorePickupReadinessTest extends TestCase
{
    public function test_unconfigured_store_does_not_advertise_pickup(): void
    {
        $store = Store::create(['name' => 'Showroom', 'slug' => 'showroom', 'phone' => '123', 'address' => 'Address', 'city' => 'City', 'is_active' => true, 'pickup_enabled' => true]);
        $data = (new StoreResource($store))->toArray(Request::create('/'));
        $this->assertFalse($data['pickup_enabled']);
        $this->assertFalse($data['has_own_stock']);
    }

    public function test_pickup_requires_active_physical_stock_at_same_address(): void
    {
        $site = PhysicalSite::create(['name' => 'Site', 'address' => 'Confirmed address']);
        $warehouse = Warehouse::create(['name' => 'Warehouse', 'source_type' => 'physical', 'stock_mode' => 'quantity', 'physical_site_id' => $site->id, 'is_active' => true]);
        $store = Store::create(['name' => 'Store', 'slug' => 'store', 'phone' => '123', 'address' => 'Address', 'city' => 'City', 'physical_site_id' => $site->id, 'warehouse_id' => $warehouse->id, 'is_active' => true, 'pickup_enabled' => true]);
        $data = fn () => (new StoreResource($store))->toArray(Request::create('/'));
        $this->assertTrue($data()['pickup_enabled']);
        $warehouse->is_active = false;
        $store->setRelation('warehouse', $warehouse);
        $this->assertFalse($data()['pickup_enabled']);
        $warehouse->is_active = true;
        $warehouse->physical_site_id = $site->id + 1;
        $this->assertFalse($data()['pickup_enabled']);
    }
}
