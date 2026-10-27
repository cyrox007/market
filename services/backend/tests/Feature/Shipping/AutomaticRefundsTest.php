<?php
namespace Tests\Feature\Shipping;

use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Services\Order\AutomaticOrderRefunds;
use App\Services\Payment\RaiffeisenEcomClient;
use App\Services\Payment\RaiffeisenEcomClientFactory;
use Tests\TestCase;

class AutomaticRefundsTest extends TestCase
{
    public function test_pending_bank_response_is_reconciled_without_a_second_refund(): void
    {
        config(['orders.automatic_cancel_refunds' => true]);
        $method = PaymentMethod::create(['name' => 'Card', 'code' => 'raif', 'gateway' => 'raiffeisen_ecom']);
        $order = Order::factory()->create(['status' => 'cancelled', 'total' => 1000]);
        $payment = $order->payments()->create(['payment_method_id' => $method->id, 'currency' => 'RUB', 'amount' => 1000, 'amount_paid' => 1000, 'status' => 'paid']);
        $client = \Mockery::mock(RaiffeisenEcomClient::class);
        $client->shouldReceive('createRefund')->once()->with($order->number, 'CANCEL-'.$payment->id, 1000.0)->andReturn(['refundStatus' => 'PENDING']);
        $client->shouldReceive('getRefundStatus')->once()->with($order->number, 'CANCEL-'.$payment->id)->andReturn(['refundStatus' => 'COMPLETED']);
        $factory = \Mockery::mock(RaiffeisenEcomClientFactory::class);
        $factory->shouldReceive('createForPayment')->twice()->andReturn($client);
        $this->app->instance(RaiffeisenEcomClientFactory::class, $factory);
        $service = app(AutomaticOrderRefunds::class);
        $service->execute($order->id);
        $this->assertDatabaseHas('order_refund_tasks', ['order_id' => $order->id, 'status' => 'manual_review']);
        $service->execute($order->id);
        $service->execute($order->id);
        $this->assertDatabaseCount('payment_refunds', 1);
        $this->assertDatabaseHas('order_refund_tasks', ['order_id' => $order->id, 'status' => 'completed', 'amount' => 0]);
    }
}
