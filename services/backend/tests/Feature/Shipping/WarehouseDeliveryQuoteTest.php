<?php

namespace Tests\Feature\Shipping;

use App\Models\Address\Address;
use App\Models\Inventory\Warehouse;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\Product;
use App\Models\User;
use App\Services\Shipping\WarehouseDeliveryQuote;
use App\Services\Shipping\WarehouseOrderStock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WarehouseDeliveryQuoteTest extends TestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->product = Product::factory()->create(['price' => 1000, 'stock' => 20]);
        Http::fake(function ($request) {
            $id = rawurldecode(basename(parse_url($request->url(), PHP_URL_PATH)));
            $region = ['kind' => 'region', 'externalId' => 'region48', 'name' => 'Липецкая область', 'kladrCode' => '4800000000000'];
            $city = ['kind' => 'locality', 'externalId' => 'city48', 'name' => 'Липецк', 'kladrCode' => '4800000100000'];
            $target = match ($id) {
                'region48' => $region, 'city48' => $city,
                default => ['kind' => 'building', 'source' => 'gar', 'externalId' => $id, 'name' => '10',
                    'latitude' => 52.6, 'longitude' => 39.6, 'coordinatePrecision' => 'building', 'coordinateSource' => 'osm'],
            };

            return Http::response(['target' => $target, 'path' => $id === 'outside' ? [['kind' => 'locality', 'externalId' => 'other', 'name' => 'Другой']] : [$region, $city], 'label' => 'Липецкая область, Липецк, д. 10']);
        });
    }

    private function warehouse(array $profile = [], float $quantity = 5, array $attributes = []): Warehouse
    {
        $warehouse = Warehouse::create(array_merge(['name' => 'Склад', 'stock_mode' => 'quantity',
            'latitude' => 52.6, 'longitude' => 39.6, 'processing_days_min' => 1, 'processing_days_max' => 2], $attributes));
        $warehouse->productStocks()->create(['product_id' => $this->product->id, 'quantity' => $quantity]);
        $warehouse->deliveryProfiles()->create(array_merge([
            'name' => 'Область', 'coverage_type' => 'locations', 'base_price' => 1500,
            'delivery_days_min' => 2, 'delivery_days_max' => 4,
            'directory_territories' => [['region_id' => 'region48']],
        ], $profile));

        return $warehouse;
    }

    private function quote(string $id = 'house', int $quantity = 2): array
    {
        return app(WarehouseDeliveryQuote::class)->calculate($id, [['product_id' => $this->product->id, 'quantity' => $quantity]]);
    }

    public function test_region_inheritance_price_and_total_days(): void
    {
        $this->warehouse();
        $quote = $this->quote();
        $this->assertTrue($quote['available']);
        $this->assertSame(1500.0, $quote['selected']['delivery_cost']);
        $this->assertSame(3, $quote['selected']['delivery_days_min']);
        $this->assertSame(6, $quote['selected']['delivery_days_max']);
        $this->assertSame(2000.0, $quote['subtotal']);
        $this->assertFalse($this->quote('outside')['available']);
    }

    public function test_quantity_duplicate_lines_and_cheapest_eligible_warehouse(): void
    {
        $this->warehouse(['base_price' => 1], 1);
        $expensive = $this->warehouse(['base_price' => 2000]);
        $cheap = $this->warehouse(['base_price' => 1000]);
        $this->assertSame($cheap->id, $this->quote()['selected']['warehouse_id']);
        $this->assertFalse($this->quote(quantity: 10)['available']);
        $this->assertCount(2, $this->quote()['options']);
        $duplicate = app(WarehouseDeliveryQuote::class)->calculate('house', [
            ['product_id' => $this->product->id, 'quantity' => 3], ['product_id' => $this->product->id, 'quantity' => 3],
        ]);
        $this->assertFalse($duplicate['available']);
    }

    public function test_radius_hybrid_kilometre_price_and_free_threshold(): void
    {
        $warehouse = $this->warehouse(['coverage_type' => 'hybrid', 'radius_km' => 10, 'price_per_km' => 50]);
        $this->assertSame(1500.0, $this->quote()['selected']['delivery_cost']);
        $warehouse->update(['latitude' => 53.6]);
        $this->assertFalse($this->quote()['available']);
        $warehouse->update(['latitude' => 52.61]);
        $this->assertGreaterThan(1500, $this->quote()['selected']['delivery_cost']);
        $warehouse->deliveryProfiles()->first()->update(['free_delivery_threshold' => 2000]);
        $this->assertSame(0.0, $this->quote()['selected']['delivery_cost']);
        $warehouse->update(['latitude' => null, 'longitude' => null]);
        $this->assertFalse($this->quote()['available']);
    }

    public function test_no_silent_split_of_a_cart_between_warehouses(): void
    {
        $first = $this->warehouse();
        $other = Product::factory()->create();
        $second = Warehouse::create(['name' => 'Второй']);
        $second->productStocks()->create(['product_id' => $other->id, 'quantity' => 5]);
        $second->deliveryProfiles()->create(['name' => 'Регион', 'base_price' => 100,
            'delivery_days_min' => 1, 'delivery_days_max' => 2, 'directory_territories' => [['region_id' => 'region48']]]);
        $result = app(WarehouseDeliveryQuote::class)->calculate('house', [
            ['product_id' => $this->product->id, 'quantity' => 1], ['product_id' => $other->id, 'quantity' => 1],
        ]);
        $this->assertFalse($result['available']);
    }

    public function test_checkout_recalculates_cost_reserves_exact_warehouse_and_returns_it_on_cancel_once(): void
    {
        $warehouse = $this->warehouse();
        PaymentMethod::create(['code' => 'card', 'name' => 'Карта', 'is_active' => true, 'is_enabled' => true, 'gateway' => 'manual']);
        $this->postJson('/api/v1/cart', ['product_id' => $this->product->id, 'quantity' => 2])->assertCreated();
        $this->postJson('/api/v1/shipping/quote', ['directory_external_id' => 'house'])->assertOk()
            ->assertJsonPath('selected.warehouse_id', $warehouse->id);
        $response = $this->postJson('/api/v1/orders', [
            'contact_name' => 'Покупатель', 'contact_phone' => '+79991234567', 'contact_email' => 'buyer@example.test',
            'payment_method' => 'card', 'delivery_type' => 'delivery', 'directory_external_id' => 'house',
            'delivery_cost' => 1, 'delivery_details' => ['apartment' => '12'],
        ])->assertCreated()->assertJsonPath('order.delivery_cost', 1500)
            ->assertJsonPath('order.fulfillment.warehouse_id', $warehouse->id);
        $order = Order::findOrFail($response->json('order.id'));
        $this->assertSame(3.0, $warehouse->productStocks()->first()->quantity);
        $this->assertSame('house', $order->address->directory_external_id);
        $this->assertSame('12', $order->address->apartment);
        $this->assertSame(3, $order->delivery_days_min);
        $order->changeStatus(\App\Models\Order\OrderStatus::CANCELLED, 'Проверка возврата');
        app(WarehouseOrderStock::class)->release($order);
        $this->assertSame(5.0, $warehouse->productStocks()->first()->quantity);
    }

    public function test_quote_does_not_allow_another_users_address(): void
    {
        $owner = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $owner->id, 'directory_external_id' => 'house']);
        $this->actingAs(User::factory()->create())->postJson('/api/v1/shipping/quote', ['address_id' => $address->id])->assertNotFound();
    }

    public function test_fixed_territory_tariff_works_without_coordinates_but_radius_does_not(): void
    {
        $this->mock(\App\Services\Address\BuildingCoordinates::class)->shouldReceive('find')->andReturnNull();
        $warehouse = $this->warehouse();
        $this->assertTrue($this->quote()['available']);
        $warehouse->deliveryProfiles()->first()->update(['coverage_type' => 'radius', 'radius_km' => 100]);
        $this->assertFalse($this->quote()['available']);
    }

    public function test_locality_tariff_priority_and_factory_preparation(): void
    {
        $warehouse = $this->warehouse(['priority' => 200], attributes: ['source_type' => 'manufacturer', 'stock_mode' => 'availability']);
        $warehouse->productAvailabilities()->create(['product_id' => $this->product->id, 'available_to_order' => true,
            'processing_days_min' => 10, 'processing_days_max' => 14]);
        $warehouse->deliveryProfiles()->create(['name' => 'Город', 'priority' => 10, 'base_price' => 500,
            'delivery_days_min' => 1, 'delivery_days_max' => 2,
            'directory_territories' => [['region_id' => 'region48', 'locality_id' => 'city48']]]);
        $result = $this->quote()['selected'];
        $this->assertSame('Город', $result['profile_name']);
        $this->assertSame(500.0, $result['delivery_cost']);
        $this->assertSame(11, $result['delivery_days_min']);
        $this->assertSame(16, $result['delivery_days_max']);
    }

    public function test_inactive_sources_and_profiles_are_not_eligible(): void
    {
        $warehouse = $this->warehouse(attributes: ['is_active' => false]);
        $this->assertFalse($this->quote()['available']);
        $warehouse->update(['is_active' => true]);
        $warehouse->deliveryProfiles()->first()->update(['is_active' => false]);
        $this->assertFalse($this->quote()['available']);
    }

    public function test_changed_stock_cannot_be_reserved_and_transaction_rolls_back(): void
    {
        $warehouse = $this->warehouse();
        $selected = $this->quote()['selected'];
        $order = Order::factory()->create(['fulfillment_snapshot' => $selected]);
        $warehouse->productStocks()->first()->update(['quantity' => 1]);
        try {
            \Illuminate\Support\Facades\DB::transaction(fn () => app(WarehouseOrderStock::class)->reserve($order));
            $this->fail('Insufficient stock must reject reservation.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('cart', $error->errors());
        }
        $this->assertSame(1.0, $warehouse->productStocks()->first()->quantity);
        $this->assertArrayNotHasKey('stock_state', $order->fresh()->fulfillment_snapshot);
    }

    public function test_changed_tariff_requires_recalculation_before_reservation(): void
    {
        $warehouse = $this->warehouse();
        $selected = $this->quote()['selected'];
        $order = Order::factory()->create(['fulfillment_snapshot' => $selected]);
        $warehouse->deliveryProfiles()->first()->update(['base_price' => 9999]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        \Illuminate\Support\Facades\DB::transaction(fn () => app(WarehouseOrderStock::class)->reserve($order));
    }

    public function test_classifier_compatibility_matches_codes_not_similar_names(): void
    {
        $coverage = app(\App\Services\Shipping\DirectoryCoverage::class);
        $territories = [['kind' => 'locality', 'externalId' => 'gar-city', 'kladrCode' => '4800000100000']];
        $this->assertTrue($coverage->matches($territories, ['path' => [['kind' => 'locality', 'externalId' => '480000010000000']]]));
        $this->assertFalse($coverage->matches($territories, ['path' => [['kind' => 'locality', 'name' => 'Липецк', 'externalId' => 'other']]]));
    }

    public function test_address_service_outage_is_not_reported_as_no_coverage(): void
    {
        $this->warehouse();
        $this->postJson('/api/v1/cart', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
        Cache::flush();
        Http::swap((new \Illuminate\Http\Client\Factory)->fake(['*' => Http::response([], 503)]));
        $this->postJson('/api/v1/shipping/quote', ['directory_external_id' => 'house'])->assertStatus(503);
    }
}
