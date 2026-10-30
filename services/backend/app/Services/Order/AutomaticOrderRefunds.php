<?php

namespace App\Services\Order;

use App\Models\Order\Order;
use App\Models\Payment\PaymentRefund;
use App\Payment\Gateways\RaiffeisenEcomGateway;
use App\Payment\Gateways\SberbankAcquiringGateway;
use App\Services\Payment\SberbankAcquiringClient;
use Vanilo\Payment\Models\PaymentStatusProxy;
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
                if ((float) $payment->amount_paid <= 0) { continue; }
                if ($payment->getMethod()?->getGateway() instanceof SberbankAcquiringGateway) {
                    $this->processSberbankRefund($order, $payment);
                    continue;
                }
                if (! ($payment->getMethod()?->getGateway() instanceof RaiffeisenEcomGateway)) { continue; }
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

    /**
     * Sberbank refunds are opt-in. Persist the one-shot intent BEFORE network
     * I/O, and reconcile exclusively via the bank status API. Never issue a
     * second POST on timeout, worker restart or an ambiguous response.
     */
    private function processSberbankRefund(Order $order, $payment): void
    {
        if (! config('orders.sberbank_auto_refunds', false)) { return; }
        $remoteId = (string) ($payment->remote_id ?? '');
        $paid = round((float) $payment->amount_paid, 2);
        if ($remoteId === '' || $paid <= 0 || ! preg_match('/^[0-9a-fA-F-]{36}$/', $remoteId)) {
            return;
        }

        // Never create a second refund beside another manual or automatic
        // payment refund (including one awaiting reconciliation).
        $refundId = 'CANCEL-SBER-' . $payment->id;
        $record = PaymentRefund::where('payment_id', $payment->id)
            ->where('refund_id', $refundId)->first();
        if ($record?->isCompleted() || $record?->isFailed()) { return; }
        if (! $record && PaymentRefund::where('payment_id', $payment->id)->exists()) { return; }

        $client = app(SberbankAcquiringClient::class);
        $bank = $client->getOrderStatusExtended($remoteId);
        $status = (int) ($bank['orderStatus'] ?? -1);
        $bankOk = is_array($bank) && $bank !== []
            && (string) ($bank['errorCode'] ?? '') === '0';
        if (! $bankOk) { return; }
        // orderStatus=4 also occurs after PARTIAL refunds. Verify the actual
        // minor-unit refunded amount before marking the payment as refunded.
        $rawRefunded = data_get($bank, 'paymentAmountInfo.refundedAmount');
        $refundedMinor = is_numeric($rawRefunded) ? (int) $rawRefunded : null;
        $expectedMinor = (int) round($paid * 100);

        if ($record) {
            if ($status === 4 && $refundedMinor === $expectedMinor) {
                $record->update(['status' => PaymentRefund::STATUS_COMPLETED,
                    'meta' => ['bank_order_status' => $status, 'verified_at' => now()->toISOString()]]);
                $payment->status = PaymentStatusProxy::REFUNDED();
                $payment->save();
            }
            return;
        }

        // Only a completely paid (captured) transaction is eligible for
        // an automatic full refund. Partial/manual cases stay on review.
        $bankAmount = (int) ($bank['amount'] ?? 0);
        if ($status !== 2 || $bankAmount !== $expectedMinor || $refundedMinor !== 0) { return; }

        $record = PaymentRefund::create([
            'payment_id' => $payment->id,
            'refund_id' => $refundId,
            'amount' => $paid,
            'status' => PaymentRefund::STATUS_IN_PROGRESS,
            'gateway' => 'sberbank_acquiring',
            'comment' => 'Возврат после отмены заказа — однократная отправка',
            'meta' => ['submitted_at' => now()->toISOString(), 'reconciliation_required' => true],
        ]);

        $result = $client->refundOrder($order, $remoteId, $paid, $refundId);
        $record->update(['meta' => array_merge($record->meta ?? [],
            ['request_result' => $result['error'] ?? 'accepted',
                'reconciliation_required' => true])]);
        // Even a 200 OK is not proof of money returned; the next invocation
        // must verify bank orderStatus=4 before marking completed.
    }
}
