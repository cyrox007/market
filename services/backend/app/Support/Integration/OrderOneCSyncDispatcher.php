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

        $record?->update(['integration_state' => 'queued']);
        $auditContext = \App\Services\Audit\AuditExecutionContext::capture('integration');
        \Illuminate\Support\Facades\DB::afterCommit(function () use ($orderId, $auditContext): void {
            try {
                $pending = SyncOrderTo1CJob::dispatch($orderId, $auditContext);
                unset($pending); // Execute PendingDispatch inside this catch, including unique-job locking.
            }
            catch (\Throwable $error) {
                Order::whereKey($orderId)->update(['integration_state' => 'failed']);
                report($error);
            }
        });
    }
}
