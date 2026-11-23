<?php

namespace App\Services\Integration;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Services\Order\OperatorOrder;
use Illuminate\Support\Facades\DB;

/**
 * Guarded inbound fulfillment progression, never a raw status overwrite.
 */
class OneCOrderStatusUpdater
{
    public function apply(int $orderId, array $data, int $tokenId, ?string $ip): array
    {
        $eventId = $data['event_id'];
        $expected = $data['expected_status'];
        $target = $data['status'];
        $comment = trim((string) ($data['comment'] ?? ''));

        $fingerprint = hash('sha256', json_encode([
            'order_id' => $orderId, 'expected_status' => $expected,
            'status' => $target, 'comment' => $comment,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($orderId, $eventId, $expected, $target, $comment, $fingerprint, $tokenId, $ip): array {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $receipt = DB::table('onec_order_status_update_events')->where('event_id', $eventId)->first();
            if ($receipt !== null) {
                abort_unless(hash_equals($receipt->payload_hash, $fingerprint), 409, 'event_id already used with different data');
                return [
                    'event_id' => $eventId, 'order_id' => $order->id, 'number' => $order->number,
                    'previous_status' => $receipt->previous_status,
                    'applied_status' => $receipt->applied_status,
                    'current_status' => $order->status, 'duplicate' => true,
                ];
            }

            abort_unless($order->status === $expected, 409, 'Order status changed; reload before retry');
            abort_unless($order->cooling_until !== null && now()->greaterThanOrEqualTo($order->cooling_until),
                409, 'Order has not passed the integration release boundary');

            $operator = app(OperatorOrder::class);
            $next = $operator->next($order);
            abort_unless($next !== null && $next['status'] === $target, 409, 'Transition is not permitted');
            abort_if($operator->blockedReason($order) !== null, 409, 'Payment must be confirmed first');

            // Native model lifecycle: history, stock, customer notifications.
            // The origin only prevents echoing this same update to 1C.
            $order->changeStatus(
                OrderStatus::from($target),
                '1С: '.($comment !== '' ? $comment : $next['label']).' ['.$eventId.']',
                null, 'onec'
            );

            DB::table('onec_order_status_update_events')->insert([
                'event_id' => $eventId, 'order_id' => $order->id,
                'token_id' => $tokenId, 'payload_hash' => $fingerprint,
                'previous_status' => $expected, 'applied_status' => $target,
                'source_ip' => $ip, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return [
                'event_id' => $eventId, 'order_id' => $order->id, 'number' => $order->number,
                'previous_status' => $expected, 'applied_status' => $target,
                'current_status' => $target, 'duplicate' => false,
            ];
        }, 3);
    }
}
