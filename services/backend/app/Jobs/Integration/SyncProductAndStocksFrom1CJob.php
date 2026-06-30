<?php

declare(strict_types=1);

namespace App\Jobs\Integration;

use App\Actions\Inventory\Sync\SyncProductStocksFrom1CByExternalIdAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductAndStocksFrom1CJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $backoff;

    public int $timeout;

    public function __construct(
        private readonly string $externalId
    ) {
        $this->queue = (string) config('catalog_import.config.svetofor_1c.minute_sync_queue', 'integration-1c');
        $this->tries = max(1, (int) config('catalog_import.config.svetofor_1c.minute_sync_job_tries', 3));
        $this->backoff = max(1, (int) config('catalog_import.config.svetofor_1c.minute_sync_job_backoff', 20));
        $this->timeout = max(1, (int) config('catalog_import.config.svetofor_1c.minute_sync_job_timeout', 120));
    }

    public function handle(SyncProductStocksFrom1CByExternalIdAction $syncAction): void
    {
        Log::info('1C minute sync job: started', [
            'external_id' => $this->externalId,
            'attempt' => $this->attempts(),
            'queue' => $this->queue,
        ]);

        $synced = $syncAction->execute($this->externalId);
        if (! $synced) {
            Log::warning('1C minute sync job: skipped by domain rules', [
                'external_id' => $this->externalId,
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        Log::info('1C minute sync job: finished', [
            'external_id' => $this->externalId,
            'attempt' => $this->attempts(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('1C minute sync job: failed', [
            'external_id' => $this->externalId,
            'error_class' => $e::class,
            'error_message' => $e->getMessage(),
        ]);
    }
}
