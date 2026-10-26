<?php

declare(strict_types=1);

namespace App\Jobs\Integration;

use App\Actions\Integration\Integration1C\BuildOrderSyncPayloadAction;
use App\Actions\Integration\Integration1C\SyncOrdersTo1CAction;
use App\Models\Order\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOrderTo1CJob implements ShouldQueue, \Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 20;
    public int $uniqueFor = 600;
    public int $timeout = 120;

    public function uniqueId(): string { return 'order:'.$this->orderId; }
    public function middleware(): array
    {
        return [(new \Illuminate\Queue\Middleware\WithoutOverlapping('order-sync:'.$this->orderId))->releaseAfter(10)->expireAfter(180)];
    }

    public function __construct(
        private readonly int $orderId
    ) {
        $this->queue = (string) (config('services.integration_1c.orders_queue') ?? 'integration-orders');
    }

    public function handle(
        BuildOrderSyncPayloadAction $buildOrderSyncPayloadAction,
        SyncOrdersTo1CAction $syncOrdersTo1CAction
    ): void {
        $order = Order::query()->find($this->orderId);
        if ($order === null) {
            return;
        }
        if ($order->cooling_until && ($order->status === 'manager_processing' || now()->lt($order->cooling_until)
            || ($order->status === 'cancelled' && ! $order->integration_sent_at))) {
            return;
        }

        $payload = $buildOrderSyncPayloadAction->execute($order);
        $ok = $syncOrdersTo1CAction->execute([$payload]);

        if (! $ok) {
            $order->update(['integration_state' => 'failed']);
            throw new \RuntimeException('Order integration did not acknowledge successful delivery.');
        }
        $order->update(['integration_state' => 'sent', 'integration_sent_at' => now()]);

        Log::info('SyncOrderTo1CJob finished', [
            'order_id' => $this->orderId,
            'order_number' => $order->number,
            'success' => $ok,
            'queue' => $this->queue,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SyncOrderTo1CJob failed', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage(),
        ]);
    }
}
