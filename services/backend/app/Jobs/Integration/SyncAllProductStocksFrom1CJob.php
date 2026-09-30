<?php

declare(strict_types=1);

namespace App\Jobs\Integration;

use App\Services\Inventory\Integrations\OneCStockSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAllProductStocksFrom1CJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $backoff;

    public int $timeout;

    public function __construct()
    {
        $this->queue = (string) config('catalog_import.config.svetofor_1c.stock_sync_queue', 'integration-1c');
        $this->tries = max(1, (int) config('catalog_import.config.svetofor_1c.stock_sync_job_tries', 3));
        $this->backoff = max(1, (int) config('catalog_import.config.svetofor_1c.stock_sync_job_backoff', 20));
        $this->timeout = max(1, (int) config('catalog_import.config.svetofor_1c.stock_sync_job_timeout', 180));
    }

    public function handle(OneCStockSyncService $service): void
    {
        Log::info('1C stock-only job: started', [
            'attempt' => $this->attempts(),
            'queue' => $this->queue,
        ]);

        $result = $service->syncAll();

        Log::info('1C stock-only job: finished', [
            'attempt' => $this->attempts(),
            ...$result,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('1C stock-only job: failed', [
            'error_class' => $e::class,
            'error_message' => $e->getMessage(),
        ]);
    }
}
