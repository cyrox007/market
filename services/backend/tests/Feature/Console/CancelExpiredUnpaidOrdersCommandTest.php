<?php

namespace Tests\Feature\Console;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Order\OrderStatus;
use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use Carbon\Carbon;
use Tests\TestCase;

class CancelExpiredUnpaidOrdersCommandTest extends TestCase
{
    public function test_it_cancels_expired_awaiting_payment_order_and_returns_stock(): void
    {
        config()->set('orders.unpaid_auto_cancel_minutes', 10);
        ProductStockSettings::getInstance()->update([
            'warehouse_accounting_enabled' => false,
        ]);

        $product = Product::factory()->create([
            'stock' => 3,
            'backorder' => false,
            'state' => 'active',
        ]);

        $order = Order::factory()->create([
            'status' => OrderStatus::AWAITING_PAYMENT->value,
            'payment_method' => 'card_ecom',
            'created_at' => Carbon::now()->subMinutes(11),
            'updated_at' => Carbon::now()->subMinutes(11),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 1000,
            'total' => 2000,
        ]);

        $this->artisan('orders:cancel-expired-unpaid')
            ->assertExitCode(0);

        $order->refresh();
        $product->refresh();

        $this->assertSame(OrderStatus::CANCELLED->value, $order->status);
        $this->assertContains((int) $product->stock, [3, 5]);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status' => OrderStatus::CANCELLED->value,
        ]);
    }
}
