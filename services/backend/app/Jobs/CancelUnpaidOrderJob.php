<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Order\AutoCancelExpiredUnpaidOrderAction;
use App\Models\Order\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CancelUnpaidOrderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 15;

    public function __construct(
        private readonly int $orderId
    ) {
        $this->queue = 'default';
    }

    public function handle(AutoCancelExpiredUnpaidOrderAction $autoCancelExpiredUnpaidOrderAction): void
    {
        $order = Order::query()->find($this->orderId);
        if ($order === null) {
            return;
        }

        $autoCancelExpiredUnpaidOrderAction->execute($order);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('CancelUnpaidOrderJob failed', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage(),
        ]);
    }
}
