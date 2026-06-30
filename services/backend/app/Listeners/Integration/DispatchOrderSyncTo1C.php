<?php

namespace App\Listeners\Integration;

use App\Events\OrderCreated;
use App\Support\Integration\OrderOneCSyncDispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchOrderSyncTo1C
{
    public function handle(OrderCreated $event): void
    {
        try {
            $orderId = $event->order->id ?? null;
            if ($orderId === null) {
                return;
            }

            OrderOneCSyncDispatcher::dispatch((int) $orderId);
        } catch (Throwable $e) {
            Log::warning('DispatchOrderSyncTo1C failed', [
                'order_id' => $event->order->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
