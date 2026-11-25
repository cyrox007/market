<?php

namespace Tests\Feature\Api;

use App\Jobs\Integration\SyncOrderTo1CJob;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\Integration\OneCOrdersAccessTokens;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class OneCOrderStatusUpdateTest extends TestCase
{
    private string $writeToken;
    private string $readToken;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'onec_order_status_write.enabled' => true,
            'onec_order_status_write.per_minute' => 120,
        ]);
        Mail::fake();

        $user = User::factory()->create();
        $tokens = app(OneCOrdersAccessTokens::class);
        $this->writeToken = $tokens->issue(
            '1C write', null, [], $user->id, null, [OneCOrdersAccessTokens::WRITE_STATUS_ABILITY]
        )['token'];
        $this->readToken = $tokens->issue('1C read', null, [], $user->id)['token'];
    }

    private function order(array $fields = []): Order
    {
        return Order::factory()->create(array_replace([
            'status' => 'assembling',
            'cooling_until' => now()->subHours(2),
            'payment_method' => null,
            'delivery_type' => 'delivery',
        ], $fields));
    }

    private function send(Order $order, array $fields = [], ?string $token = null)
    {
        return $this->withHeader('X-1C-Orders-Token', $token ?? $this->writeToken)
            ->postJson('/api/v1/integration/1c/orders/'.$order->id.'/status', array_replace([
                'event_id' => (string) Str::uuid(),
                'expected_status' => 'assembling',
                'status' => 'assembled',
            ], $fields));
    }

    public function test_disabled_read_only_and_legacy_credentials_cannot_write(): void
    {
        $order = $this->order();
        config(['onec_order_status_write.enabled' => false]);
        $this->send($order)->assertForbidden();

        config([
            'onec_order_status_write.enabled' => true,
            'onec_orders_read.enabled' => true,
            'onec_orders_read.token' => 'legacy-static-read-token-with-over-thirty-two-chars',
        ]);
        $this->send($order, [], $this->readToken)->assertUnauthorized();
        $this->send($order, [], 'legacy-static-read-token-with-over-thirty-two-chars')->assertUnauthorized();

        $this->withHeader('X-1C-Orders-Token', '')
            ->postJson('/api/v1/integration/1c/orders/'.$order->id.'/status', [])->assertUnauthorized();
        $this->assertSame('assembling', $order->fresh()->status);
    }

    public function test_write_scope_cannot_read_orders(): void
    {
        $this->order();
        $this->withHeader('X-1C-Orders-Token', $this->writeToken)
            ->getJson('/api/v1/integration/1c/orders')->assertUnauthorized();
    }

    public function test_applies_once_and_records_history_without_echo_to_onec(): void
    {
        $order = $this->order();
        $eventId = (string) Str::uuid();
        Queue::fake();
        config(['services.integration_1c.enabled' => true]);

        $data = ['event_id' => $eventId, 'comment' => 'Сборка завершена'];
        $this->send($order, $data)->assertOk()
            ->assertJsonPath('data.applied_status', 'assembled')
            ->assertJsonPath('data.duplicate', false);
        $this->send($order, $data)->assertOk()
            ->assertJsonPath('data.applied_status', 'assembled')
            ->assertJsonPath('data.duplicate', true);

        $this->assertSame('assembled', $order->fresh()->status);
        $this->assertSame(1, $order->statusHistory()->where('status', 'assembled')->count());
        $this->assertDatabaseCount('onec_order_status_update_events', 1);
        $this->assertDatabaseHas('onec_order_status_update_events', [
            'event_id' => $eventId,
            'order_id' => $order->id,
            'previous_status' => 'assembling',
            'applied_status' => 'assembled',
        ]);
        Queue::assertNotPushed(SyncOrderTo1CJob::class);
    }

    public function test_rejects_stale_event_reuse_and_skipped_steps(): void
    {
        $order = $this->order();
        $eventId = (string) Str::uuid();
        $this->send($order, ['event_id' => $eventId])->assertOk();
        $this->send($order, ['event_id' => $eventId, 'status' => 'delivered'])->assertStatus(409);
        $this->send($order)->assertStatus(409);
        $this->send($order, ['expected_status' => 'assembled', 'status' => 'delivered'])->assertStatus(409);
        $this->send($order, ['expected_status' => 'assembled', 'status' => 'cancelled'])->assertStatus(409);
        $this->send($order, ['expected_status' => 'assembled', 'status' => 'assembling'])->assertStatus(409);
        $this->assertSame('assembled', $order->fresh()->status);
    }

    public function test_cooling_and_legacy_orders_are_not_exposed_to_writes(): void
    {
        $cooling = $this->order(['status' => 'manager_processing', 'cooling_until' => now()->addHour()]);
        $this->send($cooling, ['expected_status' => 'manager_processing'])->assertStatus(409);

        $legacy = $this->order(['cooling_until' => null]);
        $this->send($legacy)->assertStatus(409);
    }

    public function test_validates_uuid_and_status_and_rejects_reuse_across_orders(): void
    {
        $first = $this->order();
        $second = $this->order();
        $id = (string) Str::uuid();
        $this->send($first, ['event_id' => $id])->assertOk();

        $this->send($second, ['event_id' => $id])->assertStatus(409);
        $this->send($second, ['event_id' => 'invalid'])->assertUnprocessable()
            ->assertJsonValidationErrors('event_id');
        $this->send($second, ['status' => 'status_from_1c_not_mapped'])->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertSame('assembling', $second->fresh()->status);
    }

    public function test_revoked_write_token_stops_working(): void
    {
        $order = $this->order();
        $row = \Illuminate\Support\Facades\DB::table('onec_orders_access_tokens')
            ->where('token_hash', hash('sha256', $this->writeToken))->first();
        app(OneCOrdersAccessTokens::class)->revoke((int) $row->id, User::factory()->create()->id);

        $this->send($order)->assertUnauthorized();
        $this->assertSame('assembling', $order->fresh()->status);
    }

    public function test_uuid_case_is_canonical_and_replay_returns_current_status(): void
    {
        $order = $this->order();
        $id = '7b7cce9f-920b-42f7-9524-72a823b7f56c';
        $this->send($order, ['event_id' => strtoupper($id)])->assertOk()
            ->assertJsonPath('data.event_id', $id);
        $this->send($order, ['expected_status' => 'assembled', 'status' => 'shipped'])->assertOk();
        $this->send($order, ['event_id' => $id])->assertOk()
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('data.current_status', 'shipped');
        $this->assertDatabaseCount('onec_order_status_update_events', 2);
    }

    public function test_unpaid_online_order_cannot_advance(): void
    {
        $method = \App\Models\Payment\PaymentMethod::create([
            'name' => 'Sber', 'code' => 'onec-test-sber', 'gateway' => 'sberbank_acquiring', 'is_active' => true,
        ]);
        $order = $this->order(['status' => 'manager_processing', 'payment_method' => $method->code, 'total' => 1500]);
        // Simulate an inconsistent imported/legacy record: normal model writes
        // already forbid entering assembly without confirmed online payment.
        \Illuminate\Support\Facades\DB::table('orders')->where('id', $order->id)->update(['status' => 'assembling']);
        $order->refresh();
        $this->send($order)->assertStatus(409)->assertJsonPath('message', 'Payment must be confirmed first');
        $this->assertSame('assembling', $order->fresh()->status);
        $this->assertDatabaseCount('onec_order_status_update_events', 0);
    }

    public function test_pickup_cannot_be_shipped_or_delivered_without_payment(): void
    {
        $order = $this->order(['status' => 'assembled', 'delivery_type' => 'pickup']);
        $this->send($order, ['expected_status' => 'assembled', 'status' => 'shipped'])->assertStatus(409);
        $this->send($order, ['expected_status' => 'assembled', 'status' => 'delivered'])->assertStatus(409);
        $this->assertSame('assembled', $order->fresh()->status);
        $this->assertDatabaseCount('onec_order_status_update_events', 0);
    }

    public function test_transition_failure_rolls_back_receipt_and_order_status(): void
    {
        $order = $this->order();
        $id = (string) Str::uuid();
        \Illuminate\Support\Facades\Event::listen(\App\Events\OrderStatusChanged::class, function ($event) use ($order): void {
            if ($event->order->id === $order->id) {
                throw new \RuntimeException('Injected lifecycle failure');
            }
        });
        $this->send($order, ['event_id' => $id])->assertStatus(500);
        $this->assertSame('assembling', $order->fresh()->status);
        $this->assertDatabaseMissing('onec_order_status_update_events', ['event_id' => $id]);
        $this->assertSame(0, $order->statusHistory()->where('status', 'assembled')->count());
    }

    public function test_write_rate_limit_is_independent_of_read(): void
    {
        config(['onec_order_status_write.per_minute' => 1, 'onec_orders_read.per_minute' => 10]);
        $order = $this->order();
        $response = $this->send($order)->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->send($order)->assertStatus(429);
        $this->withHeader('X-1C-Orders-Token', $this->readToken)
            ->getJson('/api/v1/integration/1c/orders')->assertOk();
    }
}
