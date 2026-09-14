<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Jobs\Integration\SyncOrderTo1CJob;
use App\Models\Order\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use Tests\TestCase;

class EnqueueOrders1CSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_id_option_enqueues_only_requested_order(): void
    {
        config()->set('services.integration_1c.enabled', true);
        config()->set('services.integration_1c.orders_queue', 'integration-1c');
        Queue::fake();

        Order::factory()->create();
        $target = Order::factory()->create();

        $exitCode = Artisan::call('orders:enqueue-1c-sync', [
            '--order-id' => (string) $target->id,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('поставлено заказов: 1', Artisan::output());
        Queue::assertPushed(SyncOrderTo1CJob::class, function (SyncOrderTo1CJob $job) use ($target): bool {
            $property = (new ReflectionClass($job))->getProperty('orderId');

            return $property->getValue($job) === (int) $target->id;
        });
        Queue::assertPushed(SyncOrderTo1CJob::class, 1);
    }

    public function test_invalid_order_id_fails_without_enqueuing_jobs(): void
    {
        config()->set('services.integration_1c.enabled', true);
        Queue::fake();

        $exitCode = Artisan::call('orders:enqueue-1c-sync', [
            '--order-id' => '0',
        ]);

        $this->assertSame(1, $exitCode);
        Queue::assertNothingPushed();
    }
}
