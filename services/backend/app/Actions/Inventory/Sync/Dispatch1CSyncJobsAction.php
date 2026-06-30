<?php

declare(strict_types=1);

namespace App\Actions\Inventory\Sync;

use App\Jobs\Integration\SyncProductAndStocksFrom1CJob;
use Illuminate\Support\Facades\Log;

class Dispatch1CSyncJobsAction
{
    /**
     * @param  array<int, string>  $externalIds
     * @return array{queued: int, skipped_duplicates: int}
     */
    public function execute(array $externalIds): array
    {
        if ($externalIds === []) {
            Log::warning('1C minute sync: changed list is empty, nothing to dispatch');

            return [
                'queued' => 0,
                'skipped_duplicates' => 0,
            ];
        }

        $normalized = array_values(array_filter(array_map(static fn ($id): string => trim((string) $id), $externalIds)));
        $deduplicated = array_values(array_unique($normalized));
        $skippedDuplicates = count($normalized) - count($deduplicated);

        if ($skippedDuplicates > 0) {
            Log::warning('1C minute sync: duplicates removed before dispatch', [
                'input_count' => count($normalized),
                'deduplicated_count' => count($deduplicated),
                'skipped_duplicates' => $skippedDuplicates,
            ]);
        }

        foreach ($deduplicated as $externalId) {
            SyncProductAndStocksFrom1CJob::dispatch($externalId);
        }

        Log::info('1C minute sync: jobs dispatched', [
            'queued' => count($deduplicated),
            'skipped_duplicates' => $skippedDuplicates,
            'sample_external_ids' => array_slice($deduplicated, 0, 20),
        ]);

        return [
            'queued' => count($deduplicated),
            'skipped_duplicates' => $skippedDuplicates,
        ];
    }
}
