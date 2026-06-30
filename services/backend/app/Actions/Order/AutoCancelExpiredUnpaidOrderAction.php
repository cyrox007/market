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

            if ($this->hasSuccessfulPayment($freshOrder)) {
                return false;
            }

            $freshOrder->changeStatus(OrderStatus::CANCELLED, $this->buildTimeoutComment());

            return true;
        });
    }

    private function hasSuccessfulPayment(Order $order): bool
    {
        $lastPayment = $order->payments()->orderByDesc('id')->first();
        if ($lastPayment === null) {
            return false;
        }

        if ($lastPayment->getStatus()->equals(PaymentStatusProxy::PAID())) {
            return true;
        }

        return (float) $lastPayment->getAmountPaid() > 0;
    }

    private function buildTimeoutComment(): string
    {
        $minutes = (int) config('orders.unpaid_auto_cancel_minutes', 10);
        return self::TIMEOUT_COMMENT_PREFIX . " {$minutes} минут.";
    }
}
