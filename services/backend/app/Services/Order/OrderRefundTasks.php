<?php

namespace App\Services\Order;

use App\Models\Order\Order;
use Illuminate\Support\Facades\DB;

/** Durable manual review, never report a refund before confirmation from the bank. */
class OrderRefundTasks
{
    public function ensure(Order $order): void
    {
        if ($order->status !== 'cancelled') { return; }
        $payments = $order->payments()->get();
        $paid = (float) $payments->sum('amount_paid');
        if ($paid <= 0) { return; }
        $refunded = (float) \App\Models\Payment\PaymentRefund::whereIn('payment_id', $payments->pluck('id'))->where('status', \App\Models\Payment\PaymentRefund::STATUS_COMPLETED)->sum('amount');
        $amount = max(0, $paid - $refunded);
        DB::table('order_refund_tasks')->updateOrInsert(['order_id' => $order->id], ['amount' => $amount,
            'status' => $amount > 0 ? 'manual_review' : 'completed', 'updated_at' => now()]);
    }
}
