<?php
namespace Tests\Feature\Shipping;

use App\Models\Order\Order;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PilotRecoveryTest extends TestCase
{
    public function test_recovery_retries_stale_failures_but_does_not_send_cooling_or_unsent_cancelled_orders(): void
    {
        Queue::fake();
        config(['services.integration_1c.enabled' => true, 'services.integration_1c.base_url' => 'https://receiver.invalid']);
        $base = ['cooling_until' => now()->subHour(), 'updated_at' => now()->subMinutes(20), 'integration_state' => 'failed'];
        $retry = Order::factory()->create($base + ['status' => 'assembling']);
        Order::factory()->create($base + ['status' => 'cancelled']);
        Order::factory()->create(array_replace($base, ['status' => 'manager_processing', 'cooling_until' => now()->addHour()]));
        $this->artisan('orders:recover-integration')->assertSuccessful();
        Queue::assertPushed(\App\Jobs\Integration\SyncOrderTo1CJob::class, 1);
        $this->assertSame('queued', $retry->fresh()->integration_state);
        $this->artisan('orders:recover-integration')->assertSuccessful();
        Queue::assertPushed(\App\Jobs\Integration\SyncOrderTo1CJob::class, 1);
    }

    public function test_periodic_enqueue_skips_acknowledged_and_recently_queued_orders(): void
    {
        Queue::fake();
        config(['services.integration_1c.enabled' => true]);
        $sent = Order::factory()->create(['integration_state' => 'sent', 'integration_sent_at' => now()->subDay()]);
        Order::factory()->create(['integration_state' => 'failed', 'updated_at' => now()->subMinute()]);
        $unsent = Order::factory()->create(['integration_state' => null]);
        $retry = Order::factory()->create(['integration_state' => 'failed', 'updated_at' => now()->subMinutes(20)]);

        $this->artisan('orders:enqueue-1c-sync')->assertSuccessful();
        $this->artisan('orders:enqueue-1c-sync')->assertSuccessful();

        Queue::assertPushed(\App\Jobs\Integration\SyncOrderTo1CJob::class, 2);
        foreach ([$unsent, $retry] as $order) {
            Queue::assertPushed(\App\Jobs\Integration\SyncOrderTo1CJob::class,
                fn ($job) => $job->uniqueId() === 'order:'.$order->id);
        }
        $this->assertSame('sent', $sent->fresh()->integration_state);
    }

    public function test_link_survives_reload_but_is_encrypted_and_hidden_in_serialized_model(): void
    {
        $sku = \App\Models\Product\Product::factory()->create();
        $link = \App\Models\Pricing\PrivatePriceLink::create(['name' => 'Avito', 'product_id' => $sku->id, 'price' => 100, 'expires_at' => now()->addDay()]);
        $token = $link->rotateToken();
        $this->assertSame(url('/offer/'.$token), $link->fresh()->shareUrl());
        $this->assertStringNotContainsString($token, $link->getRawOriginal('token_ciphertext'));
        $this->assertArrayNotHasKey('token_ciphertext', $link->toArray());
    }
}
