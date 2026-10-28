<?php

namespace Tests\Feature\Shipping;

use App\Actions\Integration\Integration1C\BuildOrderSyncPayloadAction;
use App\Models\Inventory\Warehouse;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\Product;
use App\Services\Product\SellableBundle;
use App\Services\Shipping\WarehouseDeliveryQuote;
use App\Services\Shipping\WarehouseOrderStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MultiWarehouseBundleDeliveryTest extends TestCase
{
    private function kit(): array
    {
        $root = Product::factory()->create([
            'name' => 'Шкаф с антресолью', 'is_variable' => true,
            'is_sellable_bundle' => true, 'price' => 0, 'stock' => 0,
        ]);
        $wardrobes = Product::factory()->create(['name' => 'Шкаф', 'is_variable' => true]);
        $tops = Product::factory()->create(['name' => 'Антресоль', 'is_variable' => true]);
        $offer = Product::factory()->create([
            'name' => 'Комплект Белый', 'parent_product_id' => $root->id,
            'price' => 0, 'stock' => 0, 'sku' => 'KIT-WHITE',
        ]);
        $wardrobe = Product::factory()->create([
            'name' => 'Шкаф белый', 'parent_product_id' => $wardrobes->id,
            'price' => 1000, 'stock' => 0, 'sku' => 'W-WHITE', 'external_id' => '1c-w-white',
        ]);
        $top = Product::factory()->create([
            'name' => 'Антресоль белая', 'parent_product_id' => $tops->id,
            'price' => 500, 'stock' => 0, 'sku' => 'T-WHITE', 'external_id' => '1c-t-white',
        ]);
        $root->sellableBundleGroups()->attach([
            $wardrobes->id => ['sort_order' => 0], $tops->id => ['sort_order' => 1],
        ]);
        app(SellableBundle::class)->saveOfferComposition($offer, [
            ['group_id' => $wardrobes->id, 'product_id' => $wardrobe->id, 'quantity' => 1],
            ['group_id' => $tops->id, 'product_id' => $top->id, 'quantity' => 1],
        ]);
        return [$root, $offer, $wardrobe, $top];
    }

    private function warehouse(Product $sku, int $quantity, int $deliveryCost, string $name): Warehouse
    {
        $warehouse = Warehouse::create([
            'name' => $name, 'is_active' => true, 'stock_mode' => 'quantity',
            'processing_days_min' => 1, 'processing_days_max' => 2,
        ]);
        $warehouse->productStocks()->create(['product_id' => $sku->id, 'quantity' => $quantity]);
        $warehouse->deliveryProfiles()->create([
            'name' => 'По области', 'coverage_type' => 'locations',
            'directory_territories' => [['region_id' => 'region48']],
            'base_price' => $deliveryCost, 'service_tariffs' => [],
            'delivery_days_min' => 1, 'delivery_days_max' => 3,
        ]);
        return $warehouse;
    }

    private function fakeAddress(): void
    {
        Http::fake(function ($request) {
            $region = ['kind' => 'region', 'externalId' => 'region48', 'name' => 'Липецкая область'];
            return Http::response([
                'target' => str_ends_with($request->url(), '/region48') ? $region
                    : ['kind' => 'building', 'externalId' => 'house', 'name' => '1'],
                'path' => [$region, ['kind' => 'locality', 'externalId' => 'lipetsk', 'name' => 'Липецк']],
                'label' => 'Липецк, тестовый дом 1',
            ]);
        });
    }

    public function test_kit_stock_is_computed_from_parts_stored_at_different_sources(): void
    {
        [$root, $offer, $wardrobe, $top] = $this->kit();
        $this->warehouse($wardrobe, 3, 100, 'Склад шкафов');
        $this->warehouse($top, 2, 200, 'Склад антресолей');

        $composition = app(SellableBundle::class)->composition($root->id, $offer->id);
        $this->assertSame(2, app(SellableBundle::class)->availableSets($composition['items']));
        $this->assertNotContains($offer->id, array_column($composition['items'], 'product_id'));
        $this->assertSame(0, (int) $offer->fresh()->stock);
        $this->assertSame(0, (int) $root->fresh()->stock);
    }

    public function test_quote_exposes_each_source_and_delivery_cost_once_per_warehouse(): void
    {
        $this->fakeAddress();
        [$root, $offer, $wardrobe, $top] = $this->kit();
        $first = $this->warehouse($wardrobe, 3, 100, 'Шкафы');
        $second = $this->warehouse($top, 3, 200, 'Антресоли');
        $composition = app(SellableBundle::class)->composition($root->id, $offer->id);

        $singleSource = app(WarehouseDeliveryQuote::class)->calculate('house', $composition['items']);
        $this->assertFalse($singleSource['available']);

        $quote = app(WarehouseDeliveryQuote::class)->calculate(
            'house', $composition['items'], null, ['allow_multi_warehouse' => true]
        );
        $this->assertTrue($quote['available']);
        $this->assertSame('multi', $quote['selected']['stock_mode']);
        $this->assertTrue($quote['selected']['multi_warehouse']);
        $this->assertCount(2, $quote['selected']['warehouse_allocations']);
        $this->assertEquals(300, $quote['selected']['delivery_cost']);
        $allocations = collect($quote['selected']['warehouse_allocations'])->keyBy('warehouse_id');
        $this->assertSame($wardrobe->id, $allocations[$first->id]['items'][0]['product_id']);
        $this->assertSame($top->id, $allocations[$second->id]['items'][0]['product_id']);

        $this->postJson('/api/v1/shipping/quote', [
            'directory_external_id' => 'house',
            'bundle_id' => $root->id, 'primary_sku_id' => $offer->id, 'bundle_quantity' => 1,
            'items' => $composition['items'],
        ])->assertOk()->assertJsonPath('selected.multi_warehouse', true)
            ->assertJsonPath('selected.delivery_cost', 300);
    }

    public function test_checkout_reserves_only_physical_skus_at_their_actual_warehouses_and_cancel_releases_both(): void
    {
        Queue::fake();
        $this->fakeAddress();
        [$root, $offer, $wardrobe, $top] = $this->kit();
        $first = $this->warehouse($wardrobe, 2, 100, 'Шкафы');
        $second = $this->warehouse($top, 2, 200, 'Антресоли');
        PaymentMethod::create([
            'name' => 'Наличные', 'code' => 'cash', 'gateway' => 'manual',
            'is_active' => true, 'is_enabled' => true,
        ]);
        $response = $this->withHeader('Idempotency-Key', 'multi-warehouse-kit')
            ->postJson('/api/v1/orders', [
                'bundle_id' => $root->id, 'primary_sku_id' => $offer->id, 'bundle_quantity' => 1,
                'items' => [
                    ['product_id' => $wardrobe->id, 'quantity' => 1],
                    ['product_id' => $top->id, 'quantity' => 1],
                ],
                'expected_subtotal' => 1500,
                'directory_external_id' => 'house', 'delivery_type' => 'delivery',
                'payment_method' => 'cash',
                'contact_name' => 'Покупатель', 'contact_phone' => '+79001234567',
                'contact_email' => 'buyer-kit@example.test',
                'accept_offer' => true, 'accept_personal_data' => true,
            ])->assertCreated()->assertJsonPath('order.delivery_cost', 300);

        $order = Order::findOrFail($response->json('order.id'));
        $this->assertSame('multi', $order->fulfillment_snapshot['stock_mode']);
        $this->assertCount(2, $order->fulfillment_snapshot['warehouse_allocations']);
        $this->assertSame([$wardrobe->id, $top->id], $order->items->pluck('product_id')->all());
        $this->assertNotContains($offer->id, $order->items->pluck('product_id')->all());
        $this->assertSame(0, (int) $offer->fresh()->stock);
        $this->assertEquals(1, $first->productStocks()->first()->reserved_quantity);
        $this->assertEquals(1, $second->productStocks()->first()->reserved_quantity);

        $payload = app(BuildOrderSyncPayloadAction::class)->execute($order);
        $this->assertNull($payload['warehouseExternalId']);
        $this->assertSame([$first->external_id, $second->external_id],
            array_column($payload['products'], 'warehouseExternalId'));
        $this->assertSame([['1c-w-white'], ['1c-t-white']],
            array_column($payload['products'], 'modifications'));

        $this->withHeader('X-Order-Token', $response->json('guest_order_token'))
            ->postJson('/api/v1/order-access/'.$order->id.'/cancel')->assertOk();
        $this->assertEquals(0, $first->productStocks()->first()->reserved_quantity);
        $this->assertEquals(0, $second->productStocks()->first()->reserved_quantity);
        $this->assertSame([], app(WarehouseOrderStock::class)->release($order));
    }

    public function test_failed_reservation_on_second_source_does_not_reserve_first_source(): void
    {
        $this->fakeAddress();
        [$root, $offer, $wardrobe, $top] = $this->kit();
        $first = $this->warehouse($wardrobe, 2, 100, 'Шкафы');
        $second = $this->warehouse($top, 2, 200, 'Антресоли');
        $composition = app(SellableBundle::class)->composition($root->id, $offer->id);
        $snapshot = app(WarehouseDeliveryQuote::class)->calculate(
            'house', $composition['items'], null, ['allow_multi_warehouse' => true]
        )['selected'];
        $order = Order::factory()->create(['fulfillment_snapshot' => $snapshot]);
        $second->productStocks()->first()->update(['quantity' => 0]);

        try {
            DB::transaction(fn () => app(WarehouseOrderStock::class)->reserve($order));
            $this->fail('Stock must fail as a whole');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cart', $e->errors());
        }
        $this->assertEquals(0, $first->productStocks()->first()->reserved_quantity);
        $this->assertArrayNotHasKey('stock_state', $order->fresh()->fulfillment_snapshot);
    }

    public function test_confirmation_consumes_reserved_quantities_at_both_original_sources(): void
    {
        $this->fakeAddress();
        [$root, $offer, $wardrobe, $top] = $this->kit();
        $first = $this->warehouse($wardrobe, 2, 100, 'Шкафы');
        $second = $this->warehouse($top, 2, 200, 'Антресоли');
        $composition = app(SellableBundle::class)->composition($root->id, $offer->id);
        $snapshot = app(WarehouseDeliveryQuote::class)->calculate('house',
            $composition['items'], null, ['allow_multi_warehouse' => true])['selected'];
        $order = Order::factory()->create([
            'fulfillment_snapshot' => $snapshot,
            'cooling_until' => now()->addHour(),
        ]);

        DB::transaction(fn () => app(WarehouseOrderStock::class)->reserve($order));
        $this->assertEquals(1, $first->productStocks()->first()->reserved_quantity);
        $this->assertEquals(1, $second->productStocks()->first()->reserved_quantity);
        app(WarehouseOrderStock::class)->consume($order);

        $this->assertSame('consumed', $order->fresh()->fulfillment_snapshot['stock_state']);
        $this->assertEquals(1, $first->productStocks()->first()->quantity);
        $this->assertEquals(1, $second->productStocks()->first()->quantity);
        $this->assertEquals(0, $first->productStocks()->first()->reserved_quantity);
        $this->assertEquals(0, $second->productStocks()->first()->reserved_quantity);
        // A repeated consume cannot deduct twice.
        app(WarehouseOrderStock::class)->consume($order);
        $this->assertEquals(1, $first->productStocks()->first()->quantity);
        $this->assertEquals(1, $second->productStocks()->first()->quantity);
    }

    public function test_public_quote_hides_nested_fingerprints_and_single_sku_is_not_split(): void
    {
        $this->fakeAddress();
        [$root, $offer, $wardrobe, $top] = $this->kit();
        $this->warehouse($wardrobe, 1, 100, 'Шкафы');
        $this->warehouse($top, 1, 200, 'Антресоли');
        $composition = app(SellableBundle::class)->composition($root->id, $offer->id);
        $response = $this->postJson('/api/v1/shipping/quote', [
            'directory_external_id' => 'house',
            'bundle_id' => $root->id, 'primary_sku_id' => $offer->id, 'bundle_quantity' => 1,
            'items' => $composition['items'],
        ])->assertOk()->assertJsonPath('selected.multi_warehouse', true);
        $this->assertStringNotContainsString('profile_fingerprint', $response->getContent());
        $this->assertStringNotContainsString('warehouse_fingerprint', $response->getContent());

        // Same SKU, quantity=2 is not satisfied by two one-unit warehouses.
        $second = $this->warehouse($wardrobe, 1, 100, 'Другие шкафы');
        $result = app(WarehouseDeliveryQuote::class)->calculate('house', [
            ['product_id' => $wardrobe->id, 'quantity' => 2],
            ['product_id' => $top->id, 'quantity' => 1],
        ], null, ['allow_multi_warehouse' => true]);
        $this->assertFalse($result['available']);
    }

    public function test_pickup_does_not_promise_unconfigured_inter_warehouse_transfer(): void
    {
        [$root, $offer, $wardrobe, $top] = $this->kit();
        $this->warehouse($wardrobe, 2, 100, 'Шкафы');
        $this->warehouse($top, 2, 200, 'Антресоли');
        $composition = app(SellableBundle::class)->composition($root->id, $offer->id);
        $this->assertSame([], app(\App\Services\Shipping\WarehousePickup::class)->options($composition['items']));
    }
}
