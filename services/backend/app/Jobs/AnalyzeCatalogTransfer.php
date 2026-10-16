<?php

namespace App\Jobs;

use App\Services\Catalog\Transfer\CatalogTransfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyzeCatalogTransfer implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [10, 30, 60];

    public function __construct(public int $runId) {}

    public function handle(CatalogTransfer $transfer): void
    {
        // One local worker across all runs, shared file lock is independent of cache driver.
        $lock = Cache::store('file')->lock('catalog-transfer:worker', 180);
        if (! $lock->get()) {
            $this->release(5);

            return;
        }
        try {
            $delay = $transfer->step($this->runId);
            if ($delay >= 0) {
                $transfer->enqueue($this->runId, $delay);
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(?\Throwable $exception): void
    {
        DB::table('catalog_transfer_runs')->where('id', $this->runId)->where('status', 'running')->update([
            'status' => 'paused', 'message' => 'Обработка остановлена после ошибки очереди. Можно продолжить.', 'updated_at' => now(),
        ]);
    }
}
