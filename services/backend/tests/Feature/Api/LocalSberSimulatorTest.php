<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Jobs\ProcessSberbankCallbackJob;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Payment\Gateways\SberbankAcquiringGateway;
use App\Services\Order\AutomaticOrderRefunds;
use App\Services\Payment\LocalSberSimulator;
use App\Services\Payment\SberbankAcquiringClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LocalSberSimulatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['local_sber.enabled' => true, 'app.url' => 'https://svetofor.local',
            'services.integration_1c.enabled' => false]);
        Http::preventStrayRequests();
    }

    private function payment(): array
    {
        $method = PaymentMethod::create(['code' => 'card_sberbank', 'name' => 'Тестовый Сбер',
            'gateway' => 'sberbank_acquiring', 'is_active' => true, 'is_enabled' => true]);
        $order = Order::factory()->create(['status' => 'awaiting_payment', 'total' => 1234.56, 'payment_method' => $method->code]);
        $payment = $order->payments()->create(['payment_method_id' => $method->id,
            'currency' => 'RUB', 'amount' => 1234.56, 'amount_paid' => 0, 'status' => 'pending']);
        $config = app(SberbankAcquiringGateway::class)->getClientConfig($method, $order);
        $this->assertStringContainsString('/__local/sber/', $config['payformUrl']);
        return [$order, $payment->fresh(), $config['payformUrl']];
    }

    private function notify(string $id): void
    {
        (new ProcessSberbankCallbackJob(['mdOrder' => $id]))->handle(app(GatewayLoggerInterface::class));
    }

    public function test_browser_success_runs_real_reconciliation_and_duplicate_callback_is_idempotent(): void
    {
        [$order, $payment, $url] = $this->payment();
        $this->get($url)->assertOk()->assertSee('Деньги не списываются');
        $action = URL::temporarySignedRoute('local-sber.decide', now()->addHour(), ['id' => $payment->remote_id]);
        $this->post($action, ['scenario' => 'success'])->assertRedirect('https://svetofor.local/orders/'.$order->id);
        $this->assertEquals(1234.56, (float) $payment->fresh()->amount_paid);
        $this->notify($payment->remote_id);
        $this->assertEquals(1234.56, (float) $payment->fresh()->amount_paid);
        Http::assertNothingSent();
    }

    public function test_timeout_and_wrong_amount_never_confirm_payment(): void
    {
        [, $payment] = $this->payment();
        $sim = app(LocalSberSimulator::class);
        $sim->decide($payment->remote_id, 'timeout');
        $this->notify($payment->remote_id);
        $this->assertSame(0.0, (float) $payment->fresh()->amount_paid);
        $sim->decide($payment->remote_id, 'success');
        DB::table('local_sber_payments')->where('id', $payment->remote_id)->update(['amount' => 1]);
        $this->notify($payment->remote_id);
        $this->assertSame(0.0, (float) $payment->fresh()->amount_paid);
    }

    public function test_declined_payment_can_be_retried_without_real_bank(): void
    {
        [$order, $payment] = $this->payment();
        app(LocalSberSimulator::class)->decide($payment->remote_id, 'decline');
        $this->notify($payment->remote_id);
        $this->assertSame(0.0, (float) $payment->fresh()->amount_paid);
        $method = PaymentMethod::where('code', 'card_sberbank')->firstOrFail();
        app(SberbankAcquiringGateway::class)->getClientConfig($method, $order, true);
        $next = $order->getLatestPayment();
        $newId = $next->remote_id;
        $this->assertNotSame($payment->remote_id, $newId);
        $this->assertSame($payment->remote_id, $payment->fresh()->remote_id);
        $this->assertCount(2, $order->payments()->get());
        app(LocalSberSimulator::class)->decide($newId, 'success');
        $this->notify($newId);
        $this->assertEquals(1234.56, (float) $next->fresh()->amount_paid);
        $this->assertSame(0.0, (float) $payment->fresh()->amount_paid);
    }

    public function test_refund_uses_persisted_intent_and_status_check_once(): void
    {
        [$order, $payment] = $this->payment();
        app(LocalSberSimulator::class)->decide($payment->remote_id, 'success');
        $this->notify($payment->remote_id);
        $order->refresh()->update(['status' => 'cancelled']);
        config(['orders.automatic_cancel_refunds' => true, 'orders.sberbank_auto_refunds' => true]);
        $refunds = app(AutomaticOrderRefunds::class);
        $refunds->execute($order->id);
        $refunds->execute($order->id);
        $refunds->execute($order->id);
        $this->assertDatabaseCount('payment_refunds', 1);
        $this->assertDatabaseHas('payment_refunds', ['payment_id' => $payment->id, 'status' => 'completed']);
        $this->assertSame('refunded', (string) $payment->fresh()->getRawOriginal('status'));
        Http::assertNothingSent();
    }

    public function test_guard_blocks_public_hosts_production_and_enabled_onec_even_with_flag(): void
    {
        config(['app.url' => 'https://new.svetofor-mebel.ru']);
        $this->assertGuarded();
        config(['app.url' => 'https://svetofor.local', 'services.integration_1c.enabled' => true]);
        $this->assertGuarded();
        config(['services.integration_1c.enabled' => false]);
        $this->app->instance('env', 'production');
        $this->assertGuarded();
    }

    public function test_late_paid_callback_keeps_cancelled_order_and_creates_refund_review(): void
    {
        [$order, $payment] = $this->payment();
        $order->update(['status' => 'cancelled']);
        app(LocalSberSimulator::class)->decide($payment->remote_id, 'success');
        $this->notify($payment->remote_id);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertEquals(1234.56, (float) $payment->fresh()->amount_paid);
        $this->assertDatabaseHas('order_refund_tasks', ['order_id' => $order->id]);
        Http::assertNothingSent();
    }


    public function test_repeated_payment_config_and_order_get_reuse_one_bank_form(): void
    {
        [$order, $payment, $url] = $this->payment();
        $user = \App\Models\User::factory()->create();
        $order->update(['user_id' => $user->id]);
        $token = $user->createToken('sber-test')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/orders/'.$order->id)
            ->assertOk()->assertJsonPath('gateway_client_config.payformUrl', $url);
        for ($i = 0; $i < 2; $i++) {
            $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/orders/'.$order->id.'/payment-config')
                ->assertOk()->assertJsonPath('gateway_client_config.payformUrl', $url);
        }
        $this->assertDatabaseCount('local_sber_payments', 1);
        $this->assertCount(1, $order->payments()->get());
        $this->assertSame($payment->remote_id, $payment->fresh()->remote_id);
    }

    public function test_scheduler_recovers_payment_without_any_callback(): void
    {
        [$order, $payment] = $this->payment();
        app(LocalSberSimulator::class)->decide($payment->remote_id, 'success');
        $this->artisan('payments:reconcile-sberbank')->assertSuccessful();
        $this->assertEquals(1234.56, (float) $payment->fresh()->amount_paid);
    }

    public function test_failed_status_check_is_recovered_by_scheduled_reconciliation(): void
    {
        [$order, $payment] = $this->payment();
        app(LocalSberSimulator::class)->decide($payment->remote_id, 'timeout');
        $this->notify($payment->remote_id);
        $this->artisan('payments:reconcile-sberbank')->assertSuccessful();
        $this->assertEquals(0, (float) $payment->fresh()->amount_paid);
        app(LocalSberSimulator::class)->decide($payment->remote_id, 'success');
        $this->travel(3)->minutes();
        $this->artisan('payments:reconcile-sberbank')->assertSuccessful();
        $this->assertEquals(1234.56, (float) $payment->fresh()->amount_paid);
    }

    public function test_expiry_does_not_cancel_when_bank_is_unreachable_and_recovers_paid_order(): void
    {
        [$order, $payment] = $this->payment();
        $order->update(['status' => 'manager_processing', 'cooling_until' => now()->subMinute()]);
        app(LocalSberSimulator::class)->decide($payment->remote_id, 'timeout');
        $cooling = app(\App\Services\Order\OrderCooling::class);
        $cooling->advance($order->id);
        $this->assertSame('manager_processing', $order->fresh()->status);
        app(LocalSberSimulator::class)->decide($payment->remote_id, 'success');
        $cooling->advance($order->id);
        $this->assertSame('assembling', $order->fresh()->status);
        $this->assertEquals(1234.56, (float) $payment->fresh()->amount_paid);
    }

    public function test_lost_registration_response_is_recovered_by_merchant_number_without_second_post(): void
    {
        [$order, $payment] = $this->payment();
        // Represent a committed intent where the bank accepted registration but
        // the application did not persist the returned ID.
        $data = $payment->data;
        $data['sberbank_registration']['state'] = 'submitted';
        unset($data['sberbank_form_url']);
        DB::table('payments')->where('id', $payment->id)->update(['remote_id' => null, 'sberbank_bank_id' => null, 'data' => json_encode($data)]);
        app(LocalSberSimulator::class)->decide($payment->remote_id, 'success');
        $this->artisan('payments:reconcile-sberbank')->assertSuccessful();
        $this->assertEquals(1234.56, (float) $payment->fresh()->amount_paid);
        $this->assertSame($payment->remote_id, $payment->fresh()->remote_id);
        $this->assertDatabaseCount('local_sber_payments', 1);
    }

    public function test_callback_of_older_attempt_is_not_discarded_when_new_attempt_exists(): void
    {
        [$order, $old] = $this->payment();
        app(LocalSberSimulator::class)->decide($old->remote_id, 'decline');
        $this->notify($old->remote_id);
        $method = PaymentMethod::where('code', 'card_sberbank')->firstOrFail();
        app(SberbankAcquiringGateway::class)->getClientConfig($method, $order, true);
        $new = $order->getLatestPayment();
        app(LocalSberSimulator::class)->decide($old->remote_id, 'success');
        $this->notify($old->remote_id);
        $this->assertEquals(1234.56, (float) $old->fresh()->amount_paid);
        $this->assertEquals(0, (float) $new->fresh()->amount_paid);
        $this->assertNull(app(SberbankAcquiringGateway::class)->getClientConfig($method, $order, true));
    }

    private function assertGuarded(): void
    {
        try {
            app(LocalSberSimulator::class)->assertEnabled();
            $this->fail('Simulator must be blocked.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_signed_form_and_disabled_simulator_cannot_be_bypassed(): void
    {
        [, $payment, $url] = $this->payment();
        $this->get('/__local/sber/'.$payment->remote_id)->assertForbidden();
        config(['local_sber.enabled' => false]);
        $this->get($url)->assertNotFound();
        $this->assertSame([], app(SberbankAcquiringClient::class)->getOrderStatusExtended($payment->remote_id));
        Http::assertNothingSent();
    }

    public function test_local_scheduler_only_advances_orders_without_scheduling_real_integrations(): void
    {
        $schedule = new \Illuminate\Console\Scheduling\Schedule('UTC');
        $this->app->instance(\Illuminate\Console\Scheduling\Schedule::class, $schedule);
        \Illuminate\Support\Facades\Schedule::clearResolvedInstance(\Illuminate\Console\Scheduling\Schedule::class);
        require base_path('routes/console.php');
        $events = $schedule->events();
        $this->assertCount(3, $events);
        $this->assertStringContainsString('payments:reconcile-sberbank', $events[0]->command);
        $this->assertStringContainsString('orders:advance-cooling', $events[1]->command);
        $this->assertStringContainsString('orders:cancel-expired-unpaid', $events[2]->command);
    }
}
