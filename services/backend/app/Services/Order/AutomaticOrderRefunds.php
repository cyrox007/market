<?php

namespace App\Services\Order;

use App\Models\Order\Order;
use App\Models\Payment\PaymentRefund;
use App\Payment\Gateways\RaiffeisenEcomGateway;
use App\Services\Payment\RaiffeisenEcomClientFactory;

/** Stable bank reference permits reconciliation after a response timeout, without a new refund. */
class AutomaticOrderRefunds
{
    public function execute(int $orderId): void
    {
        if (! config('orders.automatic_cancel_refunds')) { return; }
        \Illuminate\Support\Facades\Cache::lock('order-refund:'.$orderId, 180)->block(1, function () use ($orderId): void {
            $order = Order::findOrFail($orderId);
            if ($order->status !== 'cancelled') { return; }
            foreach ($order->payments()->get() as $payment) {
                if ((float) $payment->amount_paid <= 0 || ! ($payment->getMethod()?->getGateway() instanceof RaiffeisenEcomGateway)) { continue; }
                $refundId = 'CANCEL-'.$payment->id;
                $record = PaymentRefund::where('payment_id', $payment->id)->where('refund_id', $refundId)->first();
                if ($record?->isCompleted() || $record?->isFailed()) { continue; }
                $existing = PaymentRefund::where('payment_id', $payment->id)->where('refund_id', '!=', $refundId);
                $client = app(RaiffeisenEcomClientFactory::class)->createForPayment($payment);
                foreach ((clone $existing)->whereIn('status', ['pending', 'in_progress'])->get() as $pending) {
                    $state = $client->getRefundStatus($order->number, $pending->refund_id);
                    $bankStatus = strtoupper((string) ($state['refundStatus'] ?? $state['status'] ?? ''));
                    if ($bankStatus === 'COMPLETED') { $pending->update(['status' => 'completed']); }
                    elseif (in_array($bankStatus, ['FAILED', 'DECLINED', 'CANCELLED'], true)) { $pending->update(['status' => 'failed']); }
                }
                if ((clone $existing)->whereIn('status', ['pending', 'in_progress'])->exists()) { continue; }
                $remaining = round((float) $payment->amount_paid - (float) (clone $existing)->where('status', 'completed')->sum('amount'), 2);
                if ($remaining <= 0) { continue; }
                if ($record) {
                    $response = $client->getRefundStatus($order->number, $refundId);
                } else {
                    $record = PaymentRefund::create(['payment_id' => $payment->id, 'refund_id' => $refundId, 'amount' => $remaining,
                        'status' => 'in_progress', 'gateway' => 'raiffeisen_ecom', 'comment' => 'Возврат после отмены заказа']);
                    // Autocommit the intent before network IO; a crash cannot erase the bank reference.
                    try { $response = $client->createRefund($order->number, $refundId, $remaining); }
                    catch (\Throwable $error) {
                        $record->update(['meta' => ['reconciliation_required' => true]]);
                        report($error);
                        continue;
                    }
                }
                $status = strtoupper((string) ($response['refundStatus'] ?? $response['status'] ?? ''));
                if ($status === 'COMPLETED') { $record->update(['status' => 'completed', 'meta' => ['bank_status' => $status]]); }
                elseif (in_array($status, ['FAILED', 'DECLINED', 'CANCELLED'], true)) { $record->update(['status' => 'failed', 'meta' => ['bank_status' => $status]]); }
                // Unknown / pending response is never considered a completed bank refund.
            }
            app(OrderRefundTasks::class)->ensure($order);
        });
    }
}
