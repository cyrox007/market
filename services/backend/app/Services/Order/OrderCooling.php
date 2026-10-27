<?php

namespace App\Services\Order;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Support\Integration\OrderOneCSyncDispatcher;
use Illuminate\Support\Facades\DB;
use Vanilo\Payment\Models\PaymentStatusProxy;

class OrderCooling
{
    public function isPaid(Order $order): bool
    {
        return $order->payments()->get()->contains(fn ($p) =>
            $p->getStatus()->equals(PaymentStatusProxy::PAID()) && (float) $p->getAmountPaid() >= (float) $order->total);
    }

    public function advance(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $order = Order::lockForUpdate()->find($id);
            if (! $order || ! $order->cooling_until || now()->lt($order->cooling_until)
                || $order->status !== OrderStatus::MANAGER_PROCESSING->value) {
                return;
            }
            if (in_array($order->getPaymentGateway(), ['raiffeisen_ecom', 'raiffeisen_acquiring', 'sberbank_acquiring'], true) && ! $this->isPaid($order)) {
                $order->changeStatus(OrderStatus::CANCELLED, 'Не оплачен по истечении 60 минут охлаждения.');
                return;
            }
            $order->changeStatus(OrderStatus::ASSEMBLING, 'Период охлаждения завершён.');
            DB::afterCommit(fn () => OrderOneCSyncDispatcher::dispatch($id));
        });
    }
}
