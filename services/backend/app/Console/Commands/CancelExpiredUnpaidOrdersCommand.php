<?php

namespace App\Console\Commands;

use App\Actions\Order\AutoCancelExpiredUnpaidOrderAction;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CancelExpiredUnpaidOrdersCommand extends Command
{
    protected $signature = 'orders:cancel-expired-unpaid {--chunk=100 : Batch size for processing orders}';

    protected $description = 'Cancel unpaid awaiting_payment orders past timeout and return stock.';

    public function handle(AutoCancelExpiredUnpaidOrderAction $autoCancelExpiredUnpaidOrderAction): int
    {
        $timeoutMinutes = (int) config('orders.unpaid_auto_cancel_minutes', 10);
        $chunkSize = max(1, (int) $this->option('chunk'));
        $threshold = Carbon::now()->subMinutes($timeoutMinutes);

        $cancelled = 0;

        Order::query()
            ->where('status', OrderStatus::AWAITING_PAYMENT->value)
            ->where('created_at', '<=', $threshold)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($orders) use ($autoCancelExpiredUnpaidOrderAction, &$cancelled) {
                foreach ($orders as $order) {
                    if ($autoCancelExpiredUnpaidOrderAction->execute($order)) {
                        $cancelled++;
                    }
                }
            });

        $this->info("Auto-cancelled unpaid orders: {$cancelled}");

        return self::SUCCESS;
    }
}
