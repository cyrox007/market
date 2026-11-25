<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Services\Payment\SberbankPaymentAttempts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Vanilo\Payment\Models\PaymentProxy;

final class ReconcileSberbankPayments extends Command
{
    protected $signature = 'payments:reconcile-sberbank {--limit=20}';
    protected $description = 'Recover missing Sber registration responses and verify payments independently of callbacks';

    public function handle(SberbankPaymentAttempts $attempts): int
    {
        $methods = PaymentMethod::where('gateway', 'sberbank_acquiring')->pluck('id');
        $rows = PaymentProxy::modelClass()::query()->whereIn('payment_method_id', $methods)
            ->where('payable_type', 'order')->whereNotIn('status', ['paid', 'refunded', 'partially_refunded'])
            ->where(fn ($q) => $q->whereNotNull('remote_id')->orWhere('data->sberbank_registration->state', 'submitted'))
            ->where(fn ($q) => $q->whereNull('sberbank_reconcile_at')->orWhere('sberbank_reconcile_at', '<=', now()))
            ->orderBy('sberbank_reconcile_at')->orderBy('id')
            ->limit(max(1, min(100, (int) $this->option('limit'))))->get();
        foreach ($rows->groupBy('payable_id') as $id => $group) {
            $order = Order::find($id);
            if (! $order) { continue; }
            try {
                $verified = $attempts->reconcileOrder($order);
            } catch (\Throwable $e) {
                report($e);
                $verified = false;
            }
            // Rotate even unresolved records, so one bad payment cannot starve
            // the queue. Cancelled orders remain eligible for late settlements.
            DB::table('payments')->whereIn('id', $group->pluck('id'))->update([
                'sberbank_reconcile_at' => $verified ? now()->addMinutes($order->status === 'cancelled' ? 60 : 5) : now()->addMinutes(2),
            ]);
        }
        return self::SUCCESS;
    }
}
