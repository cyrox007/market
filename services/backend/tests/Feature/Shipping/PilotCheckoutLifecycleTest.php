<?php

namespace Tests\Feature\Shipping;

use App\Models\Inventory\Warehouse;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Pricing\PrivatePriceLink;
use App\Models\Product\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PilotCheckoutLifecycleTest extends TestCase
{
    private function scenario(): array
    {
        Queue::fake();
        Http::fake(function ($request) {
            $region = ['kind' => 'region', 'externalId' => 'region48', 'name' => 'Липецкая область'];
            return Http::response(['target' => str_ends_with($request->url(), '/region48') ? $region : ['kind' => 'building', 'externalId' => 'house', 'name' => '1'],
                'path' => [$region, ['kind' => 'locality', 'externalId' => 'lipetsk', 'name' => 'Липецк']], 'label' => 'Липецк, тестовый дом 1']);
        });
        $sku = Product::factory()->create(['price' => 1500, 'stock' => 5, 'backorder' => false]);
        $w = Warehouse::create(['name' => 'Central', 'is_active' => true]);
        $w->productStocks()->create(['product_id' => $sku->id, 'quantity' => 5]);
        $w->deliveryProfiles()->create(['name' => 'Область', 'coverage_type' => 'locations',
            'directory_territories' => [['region_id' => 'region48']], 'base_price' => 500, 'service_tariffs' => []]);
        PaymentMethod::create(['name' => 'Наличные', 'code' => 'cash', 'gateway' => 'manual', 'is_active' => true, 'is_enabled' => true]);
        return [$sku, $w, ['directory_external_id' => 'house', 'delivery_type' => 'delivery', 'warehouse_id' => $w->id,
            'payment_method' => 'cash', 'contact_name' => 'Покупатель', 'contact_phone' => '+79001234567',
            'contact_email' => 'pilot@example.test', 'accept_offer' => true, 'accept_personal_data' => true,
            'items' => [['product_id' => $sku->id, 'quantity' => 1]]]];
    }

    public function test_repeat_checkout_is_one_order_and_cancel_releases_order_reservation(): void
    {
        [$sku, $w, $body] = $this->scenario();
        $this->postJson('/api/v1/cart', ['product_id' => $sku->id, 'quantity' => 1])->assertCreated();
        $this->assertDatabaseCount('cart_stock_reservations', 1);
        $key = 'checkout-test-key-0001';
        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/orders', $body)->assertCreated();
        $this->postJson('/api/v1/orders', $body)->assertCreated()->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('order.id', $first->json('order.id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('cart_stock_reservations', 0);
        $this->assertEquals(1, $w->productStocks()->first()->reserved_quantity);
        $this->postJson('/api/v1/orders', array_replace($body, ['contact_name' => 'Другой']))->assertConflict();
        $this->withHeader('X-Order-Token', $first->json('guest_order_token'))
            ->postJson('/api/v1/order-access/'.$first->json('order.id').'/cancel')->assertOk();
        $this->assertEquals(0, $w->productStocks()->first()->reserved_quantity);
        $this->assertEquals(5, $w->productStocks()->first()->quantity);
    }

    public function test_private_price_is_locked_in_order_and_expiry_requires_new_price_confirmation(): void
    {
        [$sku, $w, $body] = $this->scenario();
        $link = PrivatePriceLink::create(['name' => 'Avito', 'product_id' => $sku->id, 'price' => 1000, 'expires_at' => now()->addMinute()]);
        $this->postJson('/api/v1/private-offers/activate', ['token' => $link->rotateToken()])->assertOk()->assertCookie('has_private_offer', '1', false);
        $this->withHeader('X-Private-Price-Context', (string) $link->id);
        $this->postJson('/api/v1/orders', $body + ['expected_subtotal' => 1000])->assertCreated()->assertJsonPath('order.subtotal', 1000);
        $this->travel(1)->minutes();
        $this->postJson('/api/v1/orders', $body + ['expected_subtotal' => 1000])->assertConflict();
        $this->postJson('/api/v1/orders', $body)->assertConflict();
        $this->postJson('/api/v1/orders', $body + ['expected_subtotal' => 1500])->assertCreated()->assertJsonPath('order.subtotal', 1500);
        $this->assertEquals(1000, Order::first()->subtotal);
    }
}
