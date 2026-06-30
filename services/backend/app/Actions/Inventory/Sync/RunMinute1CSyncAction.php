<?php

declare(strict_types=1);

namespace App\Actions\Inventory\Sync;

use App\Services\Catalog\Integrations\Svetofor1CCatalogImport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunMinute1CSyncAction
{
    public function __construct(
        private readonly Svetofor1CCatalogImport $catalogImport,
        private readonly Dispatch1CSyncJobsAction $dispatch1CSyncJobsAction
    ) {
    }

    /**
     * @return array{
     *     run_id: string,
     *     changed_after: string,
     *     next_cursor: string,
     *     imported_created: int,
     *     imported_updated: int,
     *     changed_external_ids_count: int,
     *     queued: int,
     *     skipped_duplicates: int
     * }
     */
    public function execute(string $changedAfter): array
    {
        $runId = (string) Str::uuid();

        Log::info('1C minute sync: orchestration started', [
            'run_id' => $runId,
            'changed_after' => $changedAfter,
        ]);

        $importResult = $this->catalogImport->importChangedProducts($changedAfter);
        $externalIds = $importResult['external_ids'] ?? [];
        $dispatchStats = $this->dispatch1CSyncJobsAction->execute($externalIds);
        $nextCursor = CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s.v\Z');

        Log::debug('1C minute sync: orchestration payload stats', [
            'run_id' => $runId,
            'changed_after' => $changedAfter,
            'imported_created' => $importResult['created'] ?? 0,
            'imported_updated' => $importResult['updated'] ?? 0,
            'changed_external_ids_count' => count($externalIds),
            'queued' => $dispatchStats['queued'] ?? 0,
            'skipped_duplicates' => $dispatchStats['skipped_duplicates'] ?? 0,
        ]);

        Log::info('1C minute sync: orchestration finished', [
            'run_id' => $runId,
            'next_cursor' => $nextCursor,
        ]);

        return [
            'run_id' => $runId,
            'changed_after' => $changedAfter,
            'next_cursor' => $nextCursor,
            'imported_created' => (int) ($importResult['created'] ?? 0),
            'imported_updated' => (int) ($importResult['updated'] ?? 0),
            'changed_external_ids_count' => count($externalIds),
            'queued' => (int) ($dispatchStats['queued'] ?? 0),
            'skipped_duplicates' => (int) ($dispatchStats['skipped_duplicates'] ?? 0),
        ];
    }
}
