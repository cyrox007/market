<?php

declare(strict_types=1);

namespace App\Jobs\Integration;

use App\Actions\Inventory\Sync\RefreshProductStocksFrom1CAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshProductStockFrom1CJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;
    public int $backoff;
    public int $timeout;
    public int $uniqueFor = 300;

    public function __construct(
        private readonly string $externalId
    ) {
        $this->queue = (string) config(
            'catalog_import.config.svetofor_1c.stock_refresh_queue',
            'integration-1c'
        );
        $this->tries = max(1, (int) config(
            'catalog_import.config.svetofor_1c.stock_refresh_job_tries',
            3
        ));
        $this->backoff = max(1, (int) config(
            'catalog_import.config.svetofor_1c.stock_refresh_job_backoff',
            20
        ));
        $this->timeout = max(1, (int) config(
            'catalog_import.config.svetofor_1c.stock_refresh_job_timeout',
            60
        ));
    }

    public function uniqueId(): string
    {
        return $this->externalId;
    }

    public function handle(RefreshProductStocksFrom1CAction $action): void
    {
        $result = $action->executeByExternalId($this->externalId);

        if ($result['errors'] > 0) {
            throw new \RuntimeException(
                $result['error_messages'][0] ?? '1C stock refresh failed.'
            );
        }

        Log::info('1C stock refresh job: finished', [
            'external_id' => $this->externalId,
            'synced' => $result['synced'],
            'skipped' => $result['skipped'],
            'warehouse_rows' => $result['warehouse_rows'],
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('1C stock refresh job: failed', [
            'external_id' => $this->externalId,
            'error_class' => $e::class,
            'error_message' => $e->getMessage(),
        ]);
    }
}
