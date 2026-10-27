<?php

namespace App\Actions\Order;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use Illuminate\Support\Facades\DB;
use Vanilo\Payment\Models\PaymentStatusProxy;

class AutoCancelExpiredUnpaidOrderAction
{
    public const TIMEOUT_COMMENT_PREFIX = 'Заказ автоматически отменён: оплата не поступила в течение';

    public function execute(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            /** @var Order|null $freshOrder */
            $freshOrder = Order::query()->lockForUpdate()->find($order->id);
            if ($freshOrder === null) {
                return false;
            }

            if ($freshOrder->status !== OrderStatus::AWAITING_PAYMENT->value) {
                return false;
            }

            $minutes = max(1, (int) config('orders.unpaid_auto_cancel_minutes', 10));
            if (! $freshOrder->created_at || now()->lt($freshOrder->created_at->copy()->addMinutes($minutes))) {
                return false;
            }

            if ($this->hasSuccessfulPayment($freshOrder)) {
                return false;
            }

            $freshOrder->changeStatus(OrderStatus::CANCELLED, $this->buildTimeoutComment());

            return true;
        });
    }

    private function hasSuccessfulPayment(Order $order): bool
    {
        // A newer failed attempt must not hide an earlier successful payment.
        return $order->payments()->get()->contains(fn ($payment): bool =>
            $payment->getStatus()->equals(PaymentStatusProxy::PAID()) || (float) $payment->getAmountPaid() > 0);
    }

    private function buildTimeoutComment(): string
    {
        $minutes = (int) config('orders.unpaid_auto_cancel_minutes', 10);
        return self::TIMEOUT_COMMENT_PREFIX . " {$minutes} минут.";
    }
}
