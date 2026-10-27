<?php

namespace Tests\Feature\Shipping;

use App\Models\Inventory\PhysicalSite;
use App\Models\Inventory\Warehouse;
use App\Models\Order\Order;
use App\Models\Page\Store;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Models\Shipping\ShippingLocation;
use App\Services\Inventory\WarehouseStockResolver;
use App\Services\Shipping\WarehouseOrderStock;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WarehouseOnlyFulfillmentTest extends TestCase
{
    private Product $sku;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(fn () => Http::response([
            'target' => ['kind' => 'building', 'source' => 'gar', 'externalId' => 'house', 'name' => '10'],
            'path' => [['kind' => 'region', 'externalId' => 'region48', 'name' => 'Липецкая область'], ['kind' => 'locality', 'externalId' => 'city48', 'name' => 'Липецк']],
            'label' => 'Липецк, д. 10',
        ]));
        $this->sku = Product::factory()->create(['price' => 1000, 'stock' => 999, 'backorder' => false]);
        $this->warehouse = Warehouse::create(['name' => 'Warehouse', 'address' => 'Confirmed address', 'source_type' => 'physical', 'stock_mode' => 'quantity', 'is_active' => true, 'pickup_enabled' => true]);
        $this->warehouse->productStocks()->create(['product_id' => $this->sku->id, 'quantity' => 5]);
        $this->warehouse->deliveryProfiles()->create(['name' => 'Radius', 'coverage_type' => 'radius', 'radius_km' => 10, 'base_price' => 500, 'service_tariffs' => []]);
        PaymentMethod::create(['name' => 'Cash', 'code' => 'cash', 'gateway' => 'manual', 'is_active' => true, 'is_enabled' => true]);
    }

    private function items(int $quantity = 2): array
    {
        return [['product_id' => $this->sku->id, 'quantity' => $quantity]];
    }

    private function orderData(): array
    {
        return ['contact_name' => 'Test', 'contact_phone' => '+79001234567', 'contact_email' => 'test@example.test', 'payment_method' => 'cash',
            'delivery_type' => 'pickup', 'warehouse_id' => $this->warehouse->id, 'items' => $this->items(), 'accept_offer' => true, 'accept_personal_data' => true];
    }

    public function test_all_legacy_shipping_routes_are_absent(): void
    {
        $uris = collect(Route::getRoutes())->map->uri();
        foreach (['regions', 'regions/tree', 'regions/detect', 'shipping/locations', 'shipping/locations/tree', 'shipping/locations/{locationId}',
            'shipping/calculate', 'shipping/carriers', 'shipping/shipping-methods', 'shipping/shipping-methods/calculate', 'shipping/delivery-handling-types', 'shipping/additional-services'] as $uri) {
            $this->assertNotContains('api/v1/'.$uri, $uris);
        }
        $this->assertContains('api/v1/shipping/quote', $uris);
        $this->assertContains('api/v1/shipping/pickup-options', $uris);
    }

    public function test_legacy_admin_resources_have_no_pages_or_access(): void
    {
        foreach ([\App\Filament\Resources\Shipping\ShippingLocations\ShippingLocationResource::class,
            \App\Filament\Resources\Shipping\Carriers\CarrierResource::class,
            \App\Filament\Resources\RegionShippingMethods\RegionShippingMethodResource::class] as $resource) {
            $this->assertSame([], $resource::getPages());
            $this->assertFalse($resource::canAccess());
        }
    }

    public function test_pickup_options_do_not_require_a_delivery_address(): void
    {
        $this->postJson('/api/v1/shipping/pickup-options', ['items' => $this->items()])->assertOk()
            ->assertJsonPath('pickup.0.warehouse_id', $this->warehouse->id)->assertJsonPath('pickup.0.items.0.available_quantity', 5);
        $this->postJson('/api/v1/shipping/pickup-options', ['items' => $this->items(6)])->assertOk()->assertJsonCount(0, 'pickup');
    }

    public function test_pickup_order_reserves_exact_stock_and_release_is_idempotent(): void
    {
        $this->postJson('/api/v1/orders', $this->orderData())->assertCreated();
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame(2000.0, (float) $order->total);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('reserved', $order->fulfillment_snapshot['stock_state']);
        $this->assertSame(3.0, (float) $this->warehouse->productStocks()->first()->quantity);
        app(WarehouseOrderStock::class)->release($order);
        app(WarehouseOrderStock::class)->release($order);
        $this->assertSame(5.0, (float) $this->warehouse->productStocks()->first()->quantity);
    }

    public function test_pickup_requires_warehouse_and_delivery_requires_directory_address(): void
    {
        $data = $this->orderData();
        unset($data['warehouse_id']);
        $this->postJson('/api/v1/orders', $data)->assertUnprocessable()->assertJsonValidationErrors('warehouse_id');
        $data['delivery_type'] = 'delivery';
        $this->postJson('/api/v1/orders', $data)->assertUnprocessable()->assertJsonValidationErrors('directory_external_id');
        $this->assertSame(0, Order::count());
    }

    public function test_client_prices_and_legacy_selection_are_rejected(): void
    {
        foreach (['delivery_cost' => 1, 'assembly_cost' => 1, 'shipping_location_id' => 1, 'shipping_method_id' => 1, 'address' => ['city' => 'Old']] as $field => $value) {
            $this->postJson('/api/v1/orders', [...$this->orderData(), $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame(0, Order::count());
    }

    public function test_unconfigured_delivery_does_not_fall_back_to_legacy_location(): void
    {
        $location = ShippingLocation::factory()->create(['delivery_price' => 1]);
        $this->warehouse->shippingLocations()->attach($location);
        $data = [...$this->orderData(), 'delivery_type' => 'delivery', 'directory_external_id' => 'house'];
        $this->postJson('/api/v1/orders', $data)->assertUnprocessable();
        $this->assertSame(0, Order::count());
        $this->assertSame(5.0, (float) $this->warehouse->productStocks()->first()->quantity);
    }

    public function test_old_global_stock_and_first_warehouse_switch_cannot_restore_legacy_mode(): void
    {
        ProductStockSettings::getInstance()->update(['warehouse_accounting_enabled' => false, 'fallback_to_first_warehouse' => true]);
        $this->assertTrue(ProductStockSettings::getInstance()->warehouse_accounting_enabled);
        $this->assertFalse(ProductStockSettings::getInstance()->fallback_to_first_warehouse);
        $this->assertSame(5.0, app(WarehouseStockResolver::class)->resolveForProduct($this->sku, null));
        $this->assertSame(0.0, app(WarehouseStockResolver::class)->resolveForProduct($this->sku, null,
            ['specified' => true, 'path' => [['kind' => 'locality', 'externalId' => 'outside']], 'point' => null]));
    }

    public function test_blank_shared_address_is_never_a_pickup_point(): void
    {
        $site = PhysicalSite::create(['name' => 'Incomplete', 'address' => '']);
        $this->warehouse->update(['physical_site_id' => $site->id]);
        Store::create(['name' => 'Shop', 'slug' => 'shop', 'phone' => '123', 'address' => '', 'city' => 'City',
            'warehouse_id' => $this->warehouse->id, 'physical_site_id' => $site->id, 'is_active' => true, 'pickup_enabled' => true]);
        $this->postJson('/api/v1/shipping/pickup-options', ['items' => $this->items()])->assertOk()->assertJsonCount(0, 'pickup');
        $this->getJson('/api/v1/stores')->assertOk()->assertJsonPath('data.0.pickup_enabled', false);
    }

    public function test_direct_offer_of_an_inactive_parent_is_rejected(): void
    {
        $parent = Product::factory()->create(['state' => 'inactive', 'is_variable' => true]);
        $this->sku->update(['parent_product_id' => $parent->id]);
        $this->postJson('/api/v1/shipping/pickup-options', ['items' => $this->items()])->assertUnprocessable();
    }

    public function test_deleted_store_is_not_replaced_by_warehouse_pickup(): void
    {
        $site = PhysicalSite::create(['name' => 'Site', 'address' => 'Confirmed address']);
        $this->warehouse->update(['physical_site_id' => $site->id]);
        $store = Store::create(['name' => 'Shop', 'slug' => 'shop', 'phone' => '123', 'address' => 'Confirmed address', 'city' => 'City',
            'warehouse_id' => $this->warehouse->id, 'physical_site_id' => $site->id, 'is_active' => true, 'pickup_enabled' => true]);
        $quote = app(\App\Services\Shipping\WarehousePickup::class)->select($this->warehouse->id, $store->id, $this->items());
        $order = Order::factory()->create(['fulfillment_snapshot' => $quote]);
        $store->delete();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        \Illuminate\Support\Facades\DB::transaction(fn () => app(WarehouseOrderStock::class)->reserve($order));
    }

    public function test_store_changes_invalidate_selected_pickup_conditions(): void
    {
        $site = PhysicalSite::create(['name' => 'Site', 'address' => 'Confirmed address']);
        $this->warehouse->update(['physical_site_id' => $site->id]);
        $store = Store::create(['name' => 'Shop', 'slug' => 'shop', 'phone' => '123', 'address' => 'Confirmed address', 'city' => 'City',
            'warehouse_id' => $this->warehouse->id, 'physical_site_id' => $site->id, 'is_active' => true, 'pickup_enabled' => true]);
        $quote = app(\App\Services\Shipping\WarehousePickup::class)->select($this->warehouse->id, $store->id, $this->items());
        $order = Order::factory()->create(['fulfillment_snapshot' => $quote]);
        $store->update(['hours' => 'Closed']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        \Illuminate\Support\Facades\DB::transaction(fn () => app(WarehouseOrderStock::class)->reserve($order));
    }

    public function test_null_legacy_tariffs_are_not_interpreted_as_free_services(): void
    {
        $profile = $this->warehouse->deliveryProfiles()->first();
        $profile->update(['service_tariffs' => null]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\Shipping\WarehouseServices::class)->calculate($profile, []);
    }

    public function test_pickup_never_combines_insufficient_stocks_from_different_warehouses(): void
    {
        $this->warehouse->productStocks()->first()->update(['quantity' => 1]);
        $other = Warehouse::create(['name' => 'Other', 'address' => 'Other address', 'is_active' => true, 'pickup_enabled' => true]);
        $other->productStocks()->create(['product_id' => $this->sku->id, 'quantity' => 1]);
        $this->postJson('/api/v1/shipping/pickup-options', ['items' => $this->items(2)])->assertOk()->assertJsonCount(0, 'pickup');
        $this->postJson('/api/v1/orders', $this->orderData())->assertUnprocessable();
        $this->assertSame(0, Order::count());
    }

    public function test_inactive_warehouse_is_never_offered_for_pickup(): void
    {
        $this->warehouse->update(['is_active' => false]);
        $this->postJson('/api/v1/shipping/pickup-options', ['items' => $this->items()])->assertOk()->assertJsonCount(0, 'pickup');
        $this->postJson('/api/v1/orders', $this->orderData())->assertUnprocessable();
    }
}
