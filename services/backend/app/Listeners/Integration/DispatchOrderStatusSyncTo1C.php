<?php

namespace App\Listeners\Integration;

use App\Events\OrderStatusChanged;
use App\Support\Integration\OrderOneCSyncDispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchOrderStatusSyncTo1C
{
    public function handle(OrderStatusChanged $event): void
    {
        try {
            $orderId = $event->order->id ?? null;
            if ($orderId === null) {
                return;
            }

            OrderOneCSyncDispatcher::dispatch((int) $orderId);
        } catch (Throwable $e) {
            Log::warning('DispatchOrderStatusSyncTo1C failed', [
                'order_id' => $event->order->id ?? null,
                'from' => $event->oldStatus,
                'to' => $event->newStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
