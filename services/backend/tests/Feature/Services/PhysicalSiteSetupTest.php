<?php

namespace Tests\Feature\Services;

use App\Models\Inventory\PhysicalSite;
use App\Models\Inventory\Warehouse;
use App\Models\Page\Store;
use App\Services\Address\PhysicalSiteSetup;
use App\Services\Address\WarehouseAddressResolver;
use App\Services\Shipping\WarehouseDistance;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhysicalSiteSetupTest extends TestCase
{
    private function site(): PhysicalSite
    {
        return PhysicalSite::create(['name' => 'Чаплыгин', 'address' => 'Чаплыгин, д. 10', 'city' => 'Чаплыгин',
            'address_external_id' => '123', 'kladr_code' => '123',
            'address_snapshot' => ['selectionId' => '123::10', 'path' => [['kind' => 'locality', 'name' => 'Чаплыгин']]],
            'latitude' => 53.24, 'longitude' => 39.97]);
    }

    public function test_shop_uses_existing_warehouse_without_creating_duplicate_stock(): void
    {
        $site = $this->site();
        $warehouse = Warehouse::create(['name' => 'Чаплыгин', 'external_id' => 'CH0001', 'physical_site_id' => $site->id]);
        $data = app(PhysicalSiteSetup::class)->store(['name' => 'Магазин', 'phone' => '123',
            'store_stock_mode' => 'warehouse', 'warehouse_id' => $warehouse->id, 'address' => 'Подмена']);
        $store = Store::create($data);
        $this->assertSame($site->id, $store->physical_site_id);
        $this->assertSame($warehouse->id, $store->warehouse_id);
        $this->assertSame($site->address, $store->address);
        $this->assertSame(1, Warehouse::count());
        $this->assertSame(0.0, app(WarehouseDistance::class)->kilometres($warehouse, 53.24, 39.97));
    }

    public function test_virtual_factory_cannot_be_shop_stock(): void
    {
        $factory = Warehouse::create(['name' => 'Фабрика', 'source_type' => 'manufacturer', 'stock_mode' => 'availability']);
        $this->expectException(ValidationException::class);
        app(PhysicalSiteSetup::class)->store(['store_stock_mode' => 'warehouse', 'warehouse_id' => $factory->id]);
    }

    public function test_different_site_is_not_silently_accepted(): void
    {
        $warehouse = Warehouse::create(['name' => 'Склад', 'physical_site_id' => $this->site()->id]);
        $this->expectException(ValidationException::class);
        app(PhysicalSiteSetup::class)->store(['store_stock_mode' => 'warehouse', 'warehouse_id' => $warehouse->id,
            'physical_site_id' => $this->site()->id]);
    }

    public function test_showroom_does_not_need_a_warehouse(): void
    {
        $site = $this->site();
        $data = app(PhysicalSiteSetup::class)->store(['store_stock_mode' => 'showroom', 'physical_site_id' => $site->id, 'warehouse_id' => 999]);
        $this->assertNull($data['warehouse_id']);
        $this->assertSame($site->id, $data['physical_site_id']);
    }

    public function test_separate_loading_gate_overrides_site_point(): void
    {
        $warehouse = Warehouse::create(['name' => 'Склад', 'physical_site_id' => $this->site()->id,
            'latitude' => 54, 'longitude' => 40]);
        $this->assertSame(['latitude' => 54.0, 'longitude' => 40.0], $warehouse->dispatchCoordinates());
    }

    public function test_warehouse_can_use_site_without_calling_external_directory(): void
    {
        Http::preventStrayRequests();
        $site = $this->site();
        $data = app(WarehouseAddressResolver::class)->prepare(['name' => 'Склад', 'physical_site_id' => $site->id]);
        $this->assertSame('123', $data['address_external_id']);
        $this->assertNull($data['latitude']);
        Http::assertNothingSent();
    }

    public function test_legacy_store_can_still_be_edited_without_site(): void
    {
        $store = Store::create(['name' => 'Старый', 'address' => 'Старый адрес', 'city' => 'Липецк', 'phone' => '123']);
        $data = app(PhysicalSiteSetup::class)->store(['name' => 'Новое имя', 'store_stock_mode' => 'showroom'], $store);
        $store->update($data);
        $this->assertSame('Старый адрес', $store->fresh()->address);
    }

    public function test_site_point_update_propagates_shop_coordinates(): void
    {
        Http::preventStrayRequests();
        $site = $this->site();
        $store = Store::create(['name' => 'Магазин', 'address' => 'Старый', 'city' => 'Чаплыгин', 'phone' => '123', 'physical_site_id' => $site->id]);
        app(PhysicalSiteSetup::class)->update($site, ['name' => 'Общая площадка', 'address_external_id' => '123::10', 'latitude' => 54, 'longitude' => 40]);
        $this->assertSame('54.00000000', $store->fresh()->latitude);
        $this->assertSame('123', $site->fresh()->kladr_code);
        Http::assertNothingSent();
    }

    public function test_one_c_snapshot_preserves_shop_link_and_does_not_duplicate_warehouse(): void
    {
        $site = $this->site();
        $warehouse = Warehouse::create(['name' => 'Склад', 'external_id' => 'CH0001', 'physical_site_id' => $site->id]);
        $store = Store::create(app(PhysicalSiteSetup::class)->store(['name' => 'Магазин', 'phone' => '123',
            'store_stock_mode' => 'warehouse', 'warehouse_id' => $warehouse->id]));
        $product = \App\Models\Product\Product::factory()->create();
        app(\App\Actions\Inventory\Sync\ApplyOneCStockSnapshotAction::class)->execute($product, [
            ['stockId' => 'CH0001', 'stockName' => 'Чаплыгин из 1С', 'count' => 4],
        ]);
        $this->assertSame(1, Warehouse::count());
        $this->assertSame($site->id, $warehouse->fresh()->physical_site_id);
        $this->assertSame($warehouse->id, $store->fresh()->warehouse_id);
        $this->assertEquals(4, $warehouse->productStocks()->firstOrFail()->quantity);
    }

    public function test_shop_warehouse_cannot_be_changed_to_factory(): void
    {
        $site = $this->site();
        $warehouse = Warehouse::create(['name' => 'Склад', 'physical_site_id' => $site->id]);
        Store::create(app(PhysicalSiteSetup::class)->store(['name' => 'Магазин', 'phone' => '123',
            'store_stock_mode' => 'warehouse', 'warehouse_id' => $warehouse->id]));
        $this->expectException(ValidationException::class);
        app(WarehouseAddressResolver::class)->prepare(['source_type' => 'manufacturer'], $warehouse);
    }

    public function test_store_resource_uses_live_site_point_not_stale_snapshot(): void
    {
        $site = $this->site();
        $store = Store::create(['name' => 'Магазин', 'address' => 'Старый адрес', 'city' => 'Старый город', 'phone' => '123',
            'physical_site_id' => $site->id, 'latitude' => 1, 'longitude' => 2]);
        $payload = (new \App\Http\Resources\StoreResource($store))->resolve();
        $this->assertSame($site->address, $payload['address']);
        $this->assertEquals(53.24, $payload['latitude']);
        $this->assertFalse($payload['has_own_stock']);
    }
}
