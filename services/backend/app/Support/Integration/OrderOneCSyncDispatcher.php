<?php

declare(strict_types=1);

namespace App\Support\Integration;

use App\Jobs\Integration\SyncOrderTo1CJob;
use App\Models\Order\Order;

/**
 * Постановка заказа в очередь на отправку в 1С (integration-1c).
 */
final class OrderOneCSyncDispatcher
{
    public static function isEnabled(): bool
    {
        return (bool) config('services.integration_1c.enabled', false);
    }

    public static function dispatch(Order|int $order): void
    {
        if (! self::isEnabled()) {
            return;
        }

        $orderId = $order instanceof Order ? (int) $order->id : $order;
        $record = $order instanceof Order ? $order : Order::find($orderId);
        if ($record?->cooling_until && ($record->status === 'manager_processing'
            || now()->lt($record->cooling_until) || ($record->status === 'cancelled' && ! $record->integration_sent_at))) {
            return;
        }
        if ($orderId <= 0) {
            return;
        }

        SyncOrderTo1CJob::dispatch($orderId);
    }
}
