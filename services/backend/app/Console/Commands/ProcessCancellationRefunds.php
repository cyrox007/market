<?php
namespace App\Console\Commands;

class ProcessCancellationRefunds extends \Illuminate\Console\Command
{
    protected $signature = 'orders:process-cancellation-refunds';
    protected $description = 'Reconcile and process enabled automatic cancellation refunds';
    public function handle(\App\Services\Order\AutomaticOrderRefunds $refunds): int
    {
        if (! config('orders.automatic_cancel_refunds')) { return self::SUCCESS; }
        \App\Models\Order\RefundTask::where('status', 'manual_review')->orderBy('updated_at')->limit(20)->get()
            ->each(function ($task) use ($refunds): void {
                $task->touch();
                try { $refunds->execute($task->order_id); }
                catch (\Throwable $error) { report($error); }
            });
        return self::SUCCESS;
    }
}
