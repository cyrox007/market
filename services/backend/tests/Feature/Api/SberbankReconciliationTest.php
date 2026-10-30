<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Jobs\ProcessSberbankCallbackJob;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Payment\Gateways\SberbankAcquiringGateway;
use App\Payment\Gateways\SberbankPaymentResponse;
use App\Services\Order\AutomaticOrderRefunds;
use App\Services\Payment\SberbankAcquiringClient;
use Illuminate\Http\Request;
use Tests\TestCase;
use Vanilo\Payment\Models\PaymentProxy;
use Vanilo\Payment\Models\PaymentStatusProxy;

class SberbankReconciliationTest extends TestCase
{
    private const BANK_ID = 'a67b0ced-c9a4-4cfb-bce3-b9595afaafc1';

    private function payment(string $orderStatus = 'awaiting_payment', string $paymentStatus = 'pending', float $amountPaid = 0): array
    {
        $method = PaymentMethod::create([
            'code' => 'sber-test', 'name' => 'Сбербанк',
            'gateway' => 'sberbank_acquiring', 'is_active' => true, 'is_enabled' => true,
        ]);
        $order = Order::factory()->create(['status' => $orderStatus, 'total' => 1234.56]);
        $payment = $order->payments()->create([
            'payment_method_id' => $method->id, 'currency' => 'RUB',
            'amount' => 1234.56, 'amount_paid' => $amountPaid,
            'status' => $paymentStatus, 'remote_id' => self::BANK_ID,
        ]);
        return [$order, $payment];
    }

    private function simulateSberCallback(Order $order, array $bankData): void
    {
        $mock = \Mockery::mock(SberbankAcquiringClient::class);
        $mock->shouldReceive('getOrderStatusExtended')->once()
            ->with(self::BANK_ID, $order->number)->andReturn($bankData);
        $this->app->instance(SberbankAcquiringClient::class, $mock);
        (new ProcessSberbankCallbackJob([
            'mdOrder' => self::BANK_ID, 'orderNumber' => $order->number,
            'status' => 1, 'operation' => 'deposited',
        ]))->handle(app(GatewayLoggerInterface::class));
    }

    public function test_gateway_response_follows_installed_vanilo_contract(): void
    {
        $response = new SberbankPaymentResponse('payment', true, PaymentStatusProxy::PAID(),
            null, self::BANK_ID, 1234.56);
        $this->assertTrue($response->wasSuccessful());
        $this->assertEquals(1234.56, $response->getAmountPaid());
        $this->assertEquals(1234.56, $response->getTransactionAmount());
        $this->assertNotNull($response->getNativeStatus());

        $unpaid = new SberbankPaymentResponse('payment', false, PaymentStatusProxy::DECLINED(),
            'Rejected', self::BANK_ID, 1234.56);
        $this->assertNull($unpaid->getAmountPaid());
        $this->assertSame(0.0, $unpaid->getTransactionAmount());
    }

    public function test_confirmed_bank_kopecks_are_recorded_as_rubles_exactly_once(): void
    {
        [$order, $payment] = $this->payment();
        $this->simulateSberCallback($order, ['errorCode' => 0, 'orderStatus' => 2, 'amount' => 123456]);
        $this->assertEquals(1234.56, (float) $payment->fresh()->amount_paid);
        $this->assertEquals('paid', (string) $payment->fresh()->getRawOriginal('status'));
    }

    public function test_failed_bank_status_never_records_an_amount_or_refund_task(): void
    {
        [$order, $payment] = $this->payment();
        $this->simulateSberCallback($order, ['errorCode' => 0, 'orderStatus' => 6, 'amount' => 123456]);
        $this->assertSame(0.0, (float) $payment->fresh()->amount_paid);
        $this->assertDatabaseMissing('order_refund_tasks', ['order_id' => $order->id, 'status' => 'manual_review']);
    }

    public function test_paid_status_with_wrong_amount_is_not_accepted(): void
    {
        [$order, $payment] = $this->payment();
        $this->simulateSberCallback($order, ['errorCode' => 0, 'orderStatus' => 2, 'amount' => 123455]);
        $this->assertSame(0.0, (float) $payment->fresh()->amount_paid);
    }

    public function test_sber_refund_is_opt_in_and_reconciles_without_resending(): void
    {
        [$order, $payment] = $this->payment('cancelled', 'paid', 1234.56);
        config(['orders.automatic_cancel_refunds' => true, 'orders.sberbank_auto_refunds' => false]);
        $service = app(AutomaticOrderRefunds::class);
        $service->execute($order->id);
        $this->assertDatabaseCount('payment_refunds', 0);

        config(['orders.sberbank_auto_refunds' => true]);
        $client = \Mockery::mock(SberbankAcquiringClient::class);
        $client->shouldReceive('getOrderStatusExtended')->twice()->with(self::BANK_ID)
            ->andReturn(['errorCode' => 0, 'orderStatus' => 2, 'amount' => 123456,
                    'paymentAmountInfo' => ['refundedAmount' => 0]],
                ['errorCode' => 0, 'orderStatus' => 4, 'amount' => 123456,
                    'paymentAmountInfo' => ['refundedAmount' => 123456]]);
        $client->shouldReceive('refundOrder')->once()
            ->with(\Mockery::type(Order::class), self::BANK_ID, 1234.56, 'CANCEL-SBER-'.$payment->id)
            ->andReturn(['ok' => true]);
        $this->app->instance(SberbankAcquiringClient::class, $client);
        $service->execute($order->id);
        $this->assertDatabaseHas('payment_refunds', [
            'payment_id' => $payment->id, 'status' => 'in_progress',
            'gateway' => 'sberbank_acquiring', 'amount' => 1234.56,
        ]);
        $service->execute($order->id);
        $service->execute($order->id);
        $this->assertDatabaseHas('payment_refunds', [
            'payment_id' => $payment->id, 'status' => 'completed',
        ]);
        $this->assertDatabaseCount('payment_refunds', 1);
        $this->assertEquals('refunded', (string) $payment->fresh()->getRawOriginal('status'));
        $this->assertDatabaseHas('order_refund_tasks', [
            'order_id' => $order->id, 'status' => 'completed', 'amount' => 0,
        ]);
    }

    public function test_sber_refund_timeout_stays_pending_and_never_reissues(): void
    {
        [$order, $payment] = $this->payment('cancelled', 'paid', 1234.56);
        config(['orders.automatic_cancel_refunds' => true, 'orders.sberbank_auto_refunds' => true]);
        $client = \Mockery::mock(SberbankAcquiringClient::class);
        $client->shouldReceive('getOrderStatusExtended')->twice()->with(self::BANK_ID)
            ->andReturn(['errorCode' => 0, 'orderStatus' => 2, 'amount' => 123456,
                'paymentAmountInfo' => ['refundedAmount' => 0]]);
        $client->shouldReceive('refundOrder')->once()
            ->andReturn(['ok' => false, 'error' => 'bank_unconfirmed']);
        $this->app->instance(SberbankAcquiringClient::class, $client);
        $service = app(AutomaticOrderRefunds::class);
        $service->execute($order->id);
        $service->execute($order->id);
        $this->assertDatabaseHas('payment_refunds', [
            'payment_id' => $payment->id, 'status' => 'in_progress',
        ]);
        $this->assertDatabaseCount('payment_refunds', 1);
    }

    public function test_sber_refund_http_request_converts_rubles_to_kopecks_once(): void
    {
        config([
            'payment.sberbank_acquiring.username' => 'testMerchant',
            'payment.sberbank_acquiring.password' => 'testPassword',
            'payment.sberbank_acquiring.base_url' => 'https://ecomtest.sberbank.ru',
            'payment.sberbank_acquiring.api_format' => 'ecom',
        ]);
        \Illuminate\Support\Facades\Http::fake([
            'https://ecomtest.sberbank.ru/*/refund.do' =>
                \Illuminate\Support\Facades\Http::response(['errorCode' => '0'], 200),
        ]);
        [$order] = $this->payment('cancelled', 'paid', 1234.56);
        $result = app(SberbankAcquiringClient::class)
            ->refundOrder($order, self::BANK_ID, 1234.56, 'CANCEL-SBER-TEST');
        $this->assertTrue($result['ok']);
        \Illuminate\Support\Facades\Http::assertSent(function ($request): bool {
            return str_ends_with($request->url(), '/ecomm/gw/partner/api/v1/refund.do')
                && $request->method() === 'POST'
                && $request['orderId'] === self::BANK_ID
                && $request['amount'] === 123456
                && $request->hasHeader('x-idempotencyKey', 'CANCEL-SBER-TEST');
        });
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_sber_refund_never_calls_bank_without_credentials(): void
    {
        config([
            'payment.sberbank_acquiring.username' => '',
            'payment.sberbank_acquiring.password' => '',
        ]);
        \Illuminate\Support\Facades\Http::fake();
        [$order] = $this->payment('cancelled', 'paid', 1234.56);
        $result = app(SberbankAcquiringClient::class)
            ->refundOrder($order, self::BANK_ID, 1234.56, 'CANCEL-SBER-TEST');
        $this->assertFalse($result['ok']);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_partial_bank_refund_does_not_mark_full_refund_completed(): void
    {
        [$order, $payment] = $this->payment('cancelled', 'paid', 1234.56);
        config(['orders.automatic_cancel_refunds' => true, 'orders.sberbank_auto_refunds' => true]);
        $refundId = 'CANCEL-SBER-' . $payment->id;
        \App\Models\Payment\PaymentRefund::create([
            'payment_id' => $payment->id, 'refund_id' => $refundId,
            'amount' => 1234.56, 'status' => 'in_progress',
            'gateway' => 'sberbank_acquiring',
        ]);

        $client = \Mockery::mock(SberbankAcquiringClient::class);
        $client->shouldReceive('getOrderStatusExtended')->once()->with(self::BANK_ID)
            ->andReturn(['errorCode' => 0, 'orderStatus' => 4, 'amount' => 123456,
                'paymentAmountInfo' => ['refundedAmount' => 25000]]);
        $client->shouldNotReceive('refundOrder');
        $this->app->instance(SberbankAcquiringClient::class, $client);

        app(AutomaticOrderRefunds::class)->execute($order->id);
        $this->assertDatabaseHas('payment_refunds', [
            'payment_id' => $payment->id, 'status' => 'in_progress',
        ]);
        $this->assertEquals('paid', (string) $payment->fresh()->getRawOriginal('status'));
    }

    public function test_late_paid_callback_for_cancelled_order_opens_refund_task_not_sale(): void
    {
        [$order, $payment] = $this->payment('cancelled', 'pending', 0);
        $this->simulateSberCallback($order, [
            'errorCode' => 0, 'orderStatus' => 2, 'amount' => 123456,
        ]);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertEquals(1234.56, (float) $payment->fresh()->amount_paid);
        $this->assertDatabaseHas('order_refund_tasks', [
            'order_id' => $order->id, 'status' => 'manual_review',
            'amount' => 1234.56,
        ]);
    }
}
