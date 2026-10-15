<?php

namespace Tests\Feature\Api;

use App\Models\Inventory\Warehouse;
use App\Models\Order\OneClickLead;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Product\ProductRegionRule;
use App\Models\Settings\ProductStockSettings;
use App\Models\Shipping\AdditionalService;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FulfillmentFrontContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake(function ($request) {
            $id = rawurldecode(basename(parse_url($request->url(), PHP_URL_PATH)));
            $region = ['kind' => 'region', 'externalId' => 'region48', 'name' => 'Липецкая область', 'kladrCode' => '4800000000000'];
            $city = ['kind' => 'locality', 'externalId' => 'city48', 'name' => 'Липецк', 'kladrCode' => '4800000100000'];
            $target = match ($id) {
                'region48' => $region, 'city48' => $city,
                default => ['kind' => 'building', 'externalId' => $id, 'source' => 'gar', 'name' => '10', 'latitude' => 52.6, 'longitude' => 39.6, 'coordinatePrecision' => 'address_point']
            };

            return Http::response(['target' => $target, 'path' => [$region, $city, $target], 'label' => 'Липецк, дом 10']);
        });
    }

    private function warehouse(Product $sku, array $attributes = [], array $profile = [], float $quantity = 5): Warehouse
    {
        $warehouse = Warehouse::create(array_merge(['name' => 'Склад', 'source_type' => 'physical', 'stock_mode' => 'quantity', 'latitude' => 52.6, 'longitude' => 39.6], $attributes));
        $warehouse->productStocks()->create(['product_id' => $sku->id, 'quantity' => $quantity]);
        $warehouse->deliveryProfiles()->create(array_merge(['name' => 'Город', 'base_price' => 1000, 'coverage_type' => 'locations', 'delivery_days_min' => 1, 'delivery_days_max' => 2,
            'directory_territories' => [['region_id' => 'region48', 'locality_id' => 'city48']]], $profile));

        return $warehouse;
    }

    public function test_price_facets_and_cards_exclude_inactive_variants(): void
    {
        $category = Category::factory()->create();
        $simple = Product::factory()->create(['price' => 24990]);
        $variable = Product::factory()->create(['is_variable' => true, 'price' => 34990]);
        $simple->taxons()->attach($category);
        $variable->taxons()->attach($category);
        Product::factory()->create(['parent_product_id' => $variable->id, 'price' => 34990, 'state' => 'active']);
        Product::factory()->create(['parent_product_id' => $variable->id, 'price' => 9990, 'state' => 'inactive']);
        $response = $this->getJson('/api/v1/products?category_slug='.$category->slug)->assertOk()
            ->assertJsonPath('meta.filters.price.min', 24990)->assertJsonPath('meta.filters.price.max', 34990);
        $this->assertEqualsCanonicalizing([24990, 34990], array_column($response->json('data'), 'price'));
    }

    public function test_region_prices_drive_range_filter_count_and_sort_with_local_precedence(): void
    {
        $category = Category::factory()->create();
        $parent = ShippingLocation::factory()->create();
        $city = ShippingLocation::factory()->create(['parent_id' => $parent->id]);
        $a = Product::factory()->create(['price' => 1000, 'original_price' => 1100]);
        $b = Product::factory()->create(['price' => 700]);
        $a->taxons()->attach($category);
        $b->taxons()->attach($category);
        foreach ([[$parent, 900], [$city, 500]] as [$place, $price]) {
            ProductRegionRule::create(['product_id' => $a->id, 'shipping_location_id' => $place->id, 'price_override' => $price, 'is_active' => true]);
        }
        $query = '?category_slug='.$category->slug.'&region_id='.$city->id;
        $this->getJson('/api/v1/products'.$query.'&sort_by=price&sort_order=asc')->assertOk()
            ->assertJsonPath('data.0.id', $a->id)->assertJsonPath('data.0.price', 500)
            ->assertJsonPath('meta.filters.price.min', 500)->assertJsonPath('meta.filters.price.max', 700)->assertJsonPath('meta.filters.has_sale', true);
        $this->getJson('/api/v1/products/count'.$query.'&price_max=600')->assertOk()->assertExactJson(['total' => 1]);
        $this->getJson('/api/v1/products'.$query.'&price_max=600')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_regional_stock_never_falls_back_and_disabled_profiles_remove_coverage(): void
    {
        ProductStockSettings::getInstance()->update(['warehouse_accounting_enabled' => true, 'fallback_to_first_warehouse' => true]);
        $sku = Product::factory()->create(['stock' => 99]);
        $active = $this->warehouse($sku, quantity: 7);
        $this->warehouse($sku, ['is_active' => false], quantity: 100);
        $this->getJson('/api/v1/products?directory_external_id=house')->assertOk()->assertJsonPath('data.0.stock', 7);
        $active->deliveryProfiles()->first()->update(['is_active' => false]);
        $this->getJson('/api/v1/products?directory_external_id=house')->assertOk()->assertJsonPath('data.0.stock', 0)->assertJsonPath('data.0.in_stock', false);
    }

    public function test_distance_sort_is_before_pagination_with_unknown_last(): void
    {
        $unknown = Product::factory()->create();
        $far = Product::factory()->create();
        $near = Product::factory()->create();
        $this->warehouse($unknown, ['latitude' => null, 'longitude' => null]);
        $this->warehouse($far, ['latitude' => 53.6]);
        $this->warehouse($near, ['latitude' => 52.601]);
        foreach ([1 => $near, 2 => $far, 3 => $unknown] as $page => $sku) {
            $response = $this->getJson('/api/v1/products?directory_external_id=house&sort_by=distance&per_page=1&page='.$page)->assertOk()->assertJsonPath('data.0.id', $sku->id);
            if ($page === 3) {
                $response->assertJsonPath('data.0.nearest_distance_km', null);
            }
        }
    }

    public function test_card_quote_does_not_change_cart_and_pickup_has_calendar_dates(): void
    {
        $sku = Product::factory()->create();
        $this->warehouse($sku, ['pickup_enabled' => true]);
        $response = $this->postJson('/api/v1/shipping/quote', ['directory_external_id' => 'house', 'items' => [['product_id' => $sku->id, 'quantity' => 2]]])
            ->assertOk()->assertJsonPath('selected.delivery.day_type', 'calendar')->assertJsonPath('pickup.0.ready.days_min', 0)
            ->assertJsonPath('pickup.0.ready.label', 'Сегодня')->assertJsonPath('pickup.0.items.0.available_quantity', 5);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $response->json('selected.delivery.date_min'));
        $this->assertSame(0, \Vanilo\Cart\Facades\Cart::itemCount());
        $this->postJson('/api/v1/shipping/quote', ['directory_external_id' => 'city48', 'items' => [['product_id' => $sku->id, 'quantity' => 2]]])->assertOk()->assertJsonPath('estimate', true);
    }

    public function test_fixed_services_are_server_priced_and_client_prices_rejected(): void
    {
        $sku = Product::factory()->create();
        $tariffs = ShippingLocation::factory()->create(['assembly_price' => 400]);
        $service = AdditionalService::create(['name' => 'Упаковка', 'slug' => 'packing', 'code' => 'packing', 'price_type' => 'fixed', 'base_price' => 200, 'is_active' => true]);
        $tariffs->additionalServices()->attach($service, ['price' => 300, 'is_active' => true]);
        $this->warehouse($sku, profile: ['services_location_id' => $tariffs->id]);
        $data = ['directory_external_id' => 'house', 'items' => [['product_id' => $sku->id, 'quantity' => 1]], 'requires_assembly' => true, 'additional_services' => [['id' => $service->id]]];
        $this->postJson('/api/v1/shipping/quote', $data)->assertOk()->assertJsonPath('selected.assembly_cost', 400)->assertJsonPath('selected.additional_services_cost', 300)->assertJsonPath('selected.total_fulfillment_cost', 1700);
        $data['additional_services'][0]['price'] = 1;
        $this->postJson('/api/v1/shipping/quote', $data)->assertUnprocessable()->assertJsonValidationErrors('additional_services.0.price');
    }

    public function test_delivery_order_recalculates_services_and_reserves_the_selected_stock(): void
    {
        $sku = Product::factory()->create(['stock' => 20]);
        $tariffs = ShippingLocation::factory()->create(['assembly_price' => 400]);
        $service = AdditionalService::create(['name' => 'Упаковка', 'slug' => 'packing-order', 'code' => 'packing-order', 'price_type' => 'fixed', 'base_price' => 200, 'is_active' => true]);
        $tariffs->additionalServices()->attach($service, ['price' => 300, 'is_active' => true]);
        $warehouse = $this->warehouse($sku, profile: ['services_location_id' => $tariffs->id]);
        \App\Models\Payment\PaymentMethod::create(['code' => 'card', 'name' => 'Карта', 'is_active' => true, 'is_enabled' => true, 'gateway' => 'manual']);
        $this->postJson('/api/v1/cart', ['product_id' => $sku->id, 'quantity' => 2])->assertCreated();
        $this->postJson('/api/v1/orders', ['contact_name' => 'Покупатель', 'contact_phone' => '+79991234567', 'contact_email' => 'delivery-services@example.test',
            'payment_method' => 'card', 'delivery_type' => 'delivery', 'directory_external_id' => 'house', 'warehouse_id' => $warehouse->id,
            'requires_assembly' => true, 'assembly_cost' => 1, 'delivery_cost' => 1, 'additional_services' => [['id' => $service->id]]])
            ->assertCreated()->assertJsonPath('order.assembly_cost', 400)->assertJsonPath('order.delivery_cost', 1000);
        $this->assertSame(3.0, $warehouse->productStocks()->first()->quantity);
        $order = \App\Models\Order\Order::latest('id')->firstOrFail();
        $this->assertSame(300.0, (float) $order->additionalServices()->firstOrFail()->pivot->price);
    }

    public function test_one_click_prepares_repeatable_checkout_without_leads(): void
    {
        $sku = Product::factory()->create();
        $data = ['name' => 'Покупатель', 'phone' => '89991234567', 'product_id' => $sku->id, 'quantity' => 1, 'accept_offer' => true, 'accept_personal_data' => true];
        $this->postJson('/api/v1/orders/one-click', $data)->assertOk()->assertJsonPath('mode', 'direct_checkout');
        $this->postJson('/api/v1/orders/one-click', $data)->assertOk();
        $this->assertSame(0, OneClickLead::count());
        $this->assertDatabaseCount('orders', 0);
        $data['quantity'] = 0;
        $this->postJson('/api/v1/orders/one-click', $data)->assertUnprocessable()->assertJsonValidationErrors('quantity');
    }

    public function test_selected_pickup_reserves_only_its_warehouse_and_ignores_client_assembly_price(): void
    {
        $sku = Product::factory()->create(['stock' => 20]);
        $warehouse = $this->warehouse($sku, ['pickup_enabled' => true]);
        \App\Models\Payment\PaymentMethod::create(['code' => 'card', 'name' => 'Карта', 'is_active' => true, 'is_enabled' => true, 'gateway' => 'manual']);
        $this->postJson('/api/v1/cart', ['product_id' => $sku->id, 'quantity' => 2])->assertCreated();
        $response = $this->postJson('/api/v1/orders', ['contact_name' => 'Покупатель', 'contact_phone' => '+79991234567', 'contact_email' => 'pickup@example.test',
            'payment_method' => 'card', 'delivery_type' => 'pickup', 'warehouse_id' => $warehouse->id, 'assembly_cost' => 9999])->assertCreated()
            ->assertJsonPath('order.fulfillment.mode', 'pickup')->assertJsonPath('order.fulfillment.warehouse_id', $warehouse->id)->assertJsonPath('order.assembly_cost', 0);
        $this->assertSame(3.0, $warehouse->productStocks()->first()->quantity);
        $order = \App\Models\Order\Order::findOrFail($response->json('order.id'));
        app(\App\Services\Shipping\WarehouseOrderStock::class)->release($order);
        app(\App\Services\Shipping\WarehouseOrderStock::class)->release($order);
        $this->assertSame(5.0, $warehouse->productStocks()->first()->quantity);
    }

    public function test_direct_order_uses_only_selected_sku_and_preserves_cart(): void
    {
        $sku = Product::factory()->create(['price' => 1000, 'stock' => 20]);
        $other = Product::factory()->create(['price' => 500, 'stock' => 20]);
        $warehouse = $this->warehouse($sku, ['pickup_enabled' => true]);
        \App\Models\Payment\PaymentMethod::create(['code' => 'card', 'name' => 'Карта', 'is_active' => true, 'is_enabled' => true, 'gateway' => 'manual']);
        $this->postJson('/api/v1/cart', ['product_id' => $other->id, 'quantity' => 1])->assertCreated();
        $response = $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => $sku->id, 'quantity' => 2]],
            'accept_offer' => true, 'accept_personal_data' => true,
            'contact_name' => 'Покупатель', 'contact_phone' => '+79991234567', 'contact_email' => 'direct@example.test',
            'payment_method' => 'card', 'delivery_type' => 'pickup', 'warehouse_id' => $warehouse->id,
        ])->assertCreated();
        $order = \App\Models\Order\Order::findOrFail($response->json('order.id'));
        $this->assertCount(1, $order->items);
        $this->assertSame($sku->id, $order->items->first()->product_id);
        $this->assertEquals(2000, $order->subtotal);
        $this->assertSame(3.0, $warehouse->productStocks()->first()->quantity);
        $this->assertCount(1, \Vanilo\Cart\Facades\Cart::getItems());
        $this->assertEquals($other->id, \Vanilo\Cart\Facades\Cart::getItems()->first()->product_id);
    }

    public function test_category_city_count_is_explicit_and_becomes_unknown_for_region_coverage(): void
    {
        $category = Category::factory()->create(['regional_text_template' => '{category_name}: {city}, {delivery_cities_count}']);
        $sku = Product::factory()->create();
        $sku->taxons()->attach($category);
        $warehouse = $this->warehouse($sku);
        $request = \Illuminate\Http\Request::create('/', 'GET', ['directory_external_id' => 'house']);
        $text = app(\App\Services\Product\CategoryRegionalText::class)->render($category, $request);
        $this->assertSame(1, $text['delivery_cities_count']);
        $this->assertStringContainsString('Липецк, 1', $text['text']);
        $warehouse->deliveryProfiles()->first()->update(['directory_territories' => [['region_id' => 'region48']]]);
        $text = app(\App\Services\Product\CategoryRegionalText::class)->render($category, $request);
        $this->assertNull($text['delivery_cities_count']);
        $this->assertNull($text['text']);
    }

    public function test_preparation_does_not_use_obsolete_lead_limits(): void
    {
        $sku = Product::factory()->create();
        $data = ['name' => 'Покупатель', 'phone' => '+79990000005', 'product_id' => $sku->id, 'quantity' => 1, 'accept_offer' => true, 'accept_personal_data' => true];
        $this->withHeader('Idempotency-Key', 'conflict-fixture-12345')->postJson('/api/v1/orders/one-click', $data)->assertOk();
        $data['quantity'] = 2;
        $this->postJson('/api/v1/orders/one-click', $data)->assertOk()->assertJsonPath('items.0.quantity', 2);
        $this->withHeader('Idempotency-Key', 'new-key-fixture-12345');
        \Illuminate\Support\Facades\RateLimiter::hit('one-click:phone:'.hash('sha256', $data['phone']), 600);
        for ($i = 0; $i < 4; $i++) {
            \Illuminate\Support\Facades\RateLimiter::hit('one-click:phone:'.hash('sha256', $data['phone']), 600);
        }
        $this->postJson('/api/v1/orders/one-click', $data)->assertOk();
        $this->assertSame(0, OneClickLead::count());
    }
}
