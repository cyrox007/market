<?php

namespace Tests\Feature\Shipping;

use App\Models\Inventory\Warehouse;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\Product;
use App\Services\Inventory\CartStockReservations;
use App\Services\Order\OrderCooling;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PilotLifecycleTest extends TestCase
{
    public function test_late_payment_of_cancelled_order_creates_one_review_task_and_does_not_revive_order(): void
    {
        $method = $this->method('raiffeisen_ecom');
        $order = Order::factory()->create(['status' => 'cancelled', 'cooling_until' => now()->subMinute(), 'payment_method' => $method->code, 'total' => 1000]);
        $order->payments()->create(['payment_method_id' => $method->id, 'currency' => 'RUB', 'amount' => 1000, 'amount_paid' => 1000, 'status' => 'paid']);
        app(\App\Services\Order\OrderRefundTasks::class)->ensure($order);
        app(\App\Services\Order\OrderRefundTasks::class)->ensure($order);
        $this->assertDatabaseCount('order_refund_tasks', 1);
        $this->assertDatabaseHas('order_refund_tasks', ['order_id' => $order->id, 'status' => 'manual_review', 'amount' => 1000]);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertFalse($order->fresh()->canPayOnline());
    }

    private function method(string $gateway = 'manual'): PaymentMethod
    {
        return PaymentMethod::create(['name' => 'Test', 'code' => 'pilot-'.$gateway, 'gateway' => $gateway, 'configuration' => [], 'is_active' => true]);
    }

    public function test_cash_order_cannot_be_assembled_early_and_cannot_be_cancelled_after_deadline(): void
    {
        Event::fake([\App\Events\OrderCreated::class, \App\Events\OrderStatusChanged::class]);
        \Illuminate\Support\Facades\Queue::fake();
        $order = Order::factory()->create(['status' => 'manager_processing', 'cooling_until' => now()->addHour(), 'payment_method' => $this->method()->code]);
        $this->assertTrue($order->canBeCancelled());
        app(OrderCooling::class)->advance($order->id);
        $this->assertSame('manager_processing', $order->fresh()->status);
        $this->travel(60)->minutes();
        $this->assertFalse($order->fresh()->canBeCancelled());
        app(OrderCooling::class)->advance($order->id);
        $this->assertSame('assembling', $order->fresh()->status);
        app(OrderCooling::class)->advance($order->id);
        $this->assertSame(1, $order->statusHistory()->count());
    }

    public function test_unpaid_card_is_cancelled_but_paid_card_waits_full_hour(): void
    {
        Event::fake([\App\Events\OrderCreated::class, \App\Events\OrderStatusChanged::class]);
        \Illuminate\Support\Facades\Queue::fake();
        $method = $this->method('raiffeisen_ecom');
        $paid = Order::factory()->create(['status' => 'manager_processing', 'cooling_until' => now()->addHour(), 'payment_method' => $method->code, 'total' => 1000]);
        $unpaid = Order::factory()->create(['status' => 'manager_processing', 'cooling_until' => now()->addHour(), 'payment_method' => $method->code]);
        $paid->payments()->create(['payment_method_id' => $method->id, 'currency' => 'RUB', 'amount' => 1000, 'amount_paid' => 1000, 'status' => 'paid']);
        $this->assertFalse($paid->canPayOnline());
        $this->assertTrue($paid->canBeCancelled());
        $this->travel(60)->minutes();
        app(OrderCooling::class)->advance($paid->id);
        app(OrderCooling::class)->advance($unpaid->id);
        $this->assertSame('assembling', $paid->fresh()->status);
        $this->assertSame('cancelled', $unpaid->fresh()->status);
    }

    public function test_cart_holds_exclude_other_buyers_expire_and_are_not_extended_by_repeat(): void
    {
        $sku = Product::factory()->create();
        $w = Warehouse::create(['name' => 'Central', 'is_active' => true]);
        $w->productStocks()->create(['product_id' => $sku->id, 'quantity' => 1]);
        $service = app(CartStockReservations::class);
        request()->attributes->set('cart_reservation_owner', str_repeat('a', 64));
        $service->reserve($sku, 1);
        $expiry = $service->forProduct($sku->id)->expires_at;
        $this->travel(10)->minutes();
        $service->reserve($sku, 1);
        $this->assertSame($expiry, $service->forProduct($sku->id)->expires_at);
        request()->attributes->set('cart_reservation_owner', str_repeat('b', 64));
        $this->assertSame(0.0, $service->available($w->id, $sku->id, 1));
        $this->travel(20)->minutes();
        $this->assertSame(1.0, $service->available($w->id, $sku->id, 1));
        $service->reserve($sku, 1);
        $this->assertNotNull($service->forProduct($sku->id));
    }

    public function test_guest_cannot_read_other_order_and_late_cancel_creates_request(): void
    {
        $token = str_repeat('b', 64);
        $order = Order::factory()->create(['status' => 'manager_processing', 'cooling_until' => now()->addHour(), 'guest_token_hash' => hash('sha256', $token)]);
        $this->getJson('/api/v1/order-access/'.$order->id)->assertForbidden();
        $this->withHeader('X-Order-Token', $token)->getJson('/api/v1/order-access/'.$order->id)->assertOk();
        $this->travel(60)->minutes();
        $this->postJson('/api/v1/order-access/'.$order->id.'/cancel')->assertUnprocessable();
        $this->postJson('/api/v1/order-access/'.$order->id.'/cancellation-request', ['reason' => 'Прошу отменить'])->assertStatus(202);
        $this->postJson('/api/v1/order-access/'.$order->id.'/cancellation-request', ['reason' => 'Повтор'])->assertStatus(202);
        $this->assertDatabaseCount('order_cancellation_requests', 1);
    }
}
