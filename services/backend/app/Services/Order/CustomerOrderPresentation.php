<?php
namespace App\Services\Order;

use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only customer view. Does not drive warehouse, payment or order transitions. */
class CustomerOrderPresentation
{
    public function preload(iterable $orders): void
    {
        $orders = new Collection(collect($orders)->all());
        $orders->loadMissing([
            'statusHistory' => fn ($q) => $q->select(['id', 'order_id', 'status', 'created_at']),
            'payments' => fn ($q) => $q->select(['id', 'payable_id', 'payable_type', 'status', 'amount_paid']),
            'payments.history' => fn ($q) => $q->where('new_status', 'paid')->select(['id', 'payment_id', 'new_status', 'created_at']),
            'checkoutPaymentMethod', 'refundTask',
        ]);
        $refunds = DB::table('payment_refunds')->whereIn('payment_id', $orders->flatMap(fn ($o) => $o->payments->pluck('id')))
            ->get(['payment_id', 'amount', 'status'])->groupBy('payment_id');
        foreach ($orders as $order) {
            $order->setRelation('customerRefunds', $order->payments->flatMap(fn ($p) => $refunds->get($p->id, collect())));
        }
    }

    public function group(?string $status): string
    {
        if (in_array($status, config('customer_orders.completed_statuses'), true)) { return 'completed'; }
        if (in_array($status, config('customer_orders.cancelled_statuses'), true)) { return 'cancelled'; }
        return 'active';
    }

    public function present(Order $order): array
    {
        if (! $order->relationLoaded('customerRefunds')) { $this->preload([$order]); }
        $definitions = config('customer_orders.statuses');
        $definition = $definitions[$order->status] ?? ['label' => 'Обрабатывается', 'step' => null];
        $pickup = $order->delivery_type === 'pickup';
        $label = $pickup && $order->status === 'delivered' ? 'Получен' : $definition['label'];
        $steps = config('customer_orders.progress.'.($pickup ? 'pickup' : 'delivery'));
        $keys = array_keys($steps);
        $dates = ['created' => $order->created_at?->toISOString()];
        $max = 0;
        foreach ($order->statusHistory->sortBy('created_at') as $entry) {
            $step = $definitions[$entry->status]['step'] ?? null;
            $index = array_search($step, $keys, true);
            if ($index !== false) {
                $max = max($max, $index);
                // Date of the first actual event for the displayed milestone.
                $dates[$step] ??= $entry->created_at?->toISOString();
            }
        }
        $current = array_search($definition['step'], $keys, true);
        $current = $current === false ? $max : max($current, $max);
        $cancelled = $this->group($order->status) === 'cancelled';
        $completed = $this->group($order->status) === 'completed';
        $progress = [];
        foreach ($steps as $code => $stepLabel) {
            $index = array_search($code, $keys, true);
            $state = $cancelled ? ($index <= $max ? 'completed' : 'skipped')
                : ($completed || $index < $current ? 'completed' : ($index === $current ? 'current' : 'pending'));
            $progress[] = ['code' => $code, 'label' => $stepLabel, 'state' => $state, 'occurred_at' => $dates[$code] ?? null];
        }
        if ($cancelled) {
            $progress[] = ['code' => 'cancelled', 'label' => 'Отменён', 'state' => 'cancelled',
                'occurred_at' => $order->statusHistory->where('status', 'cancelled')->sortBy('created_at')->first()?->created_at?->toISOString()];
        }
        return [
            'version' => 1,
            'group' => $this->group($order->status),
            'status' => ['code' => $order->status, 'label' => $label],
            'payment' => $this->payment($order),
            'progress' => $progress,
            'message' => $order->status === 'in_transit' ? 'Заказ в пути' : null,
        ];
    }

    private function payment(Order $order): array
    {
        $paid = round((float) $order->payments->sum('amount_paid'), 2);
        $refunds = $order->getRelation('customerRefunds');
        $refunded = round((float) $refunds->where('status', 'completed')->sum('amount'), 2);
        $pendingRefund = $refunds->whereIn('status', ['pending', 'in_progress'])->isNotEmpty();
        $hasRefundTask = $order->refundTask && $order->refundTask->status !== 'completed';
        $paidAt = null;
        if ($paid > 0 && $paid >= (float) $order->total) {
            $datedPayments = $order->payments->filter(fn ($p) => (float) $p->amount_paid > 0)->map(function ($p) {
                $date = $p->history->filter(fn ($h) => $h->getRawOriginal('new_status') === 'paid')->sortBy('created_at')->first()?->created_at;
                return ['amount' => (float) $p->amount_paid, 'date' => $date];
            });
            if ($datedPayments->every(fn ($p) => $p['date'] !== null)) {
                $sum = 0;
                foreach ($datedPayments->sortBy(fn ($p) => $p['date']->getTimestamp()) as $payment) {
                    $sum += $payment['amount'];
                    if ($sum >= (float) $order->total) { $paidAt = $payment['date']->toISOString(); break; }
                }
            }
        }
        $status = 'unpaid'; $label = 'Ожидает оплаты';
        if ($paid > 0) {
            [$status, $label] = $paid >= (float) $order->total ? ['paid', 'Оплачен'] : ['partially_paid', 'Частично оплачен'];
        } elseif ($order->checkoutPaymentMethod?->gateway === 'cash' || $order->payment_method === 'cash') {
            [$status, $label] = ['pay_on_receipt', 'Оплата при получении'];
        }
        if ($order->status === 'cancelled' && $paid <= 0) {
            [$status, $label] = ['unpaid', 'Не оплачен'];
        }
        if ($refunded > 0) {
            [$status, $label] = $refunded >= $paid ? ['refunded', 'Деньги возвращены'] : ['partially_refunded', 'Частичный возврат'];
        }
        $refundStatus = $pendingRefund ? 'pending' : ($hasRefundTask ? 'manual_review' : ($refunded > 0 ? 'completed' : 'none'));
        $refundLabel = match ($refundStatus) {
            'pending' => 'Возврат обрабатывается',
            'manual_review' => 'Возврат согласовывается',
            'completed' => $status === 'refunded' ? 'Деньги возвращены' : 'Частичный возврат',
            default => null,
        };
        return ['status' => $status, 'label' => $label, 'paid_at' => $paidAt,
            'amount_paid' => $paid, 'amount_refunded' => $refunded,
            'refund_status' => $refundStatus, 'refund_label' => $refundLabel];
    }
}
