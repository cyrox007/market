<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Inventory\Sync\Get1CSyncCursorAction;
use App\Actions\Inventory\Sync\RunMinute1CSyncAction;
use App\Actions\Inventory\Sync\Store1CSyncCursorAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class InventorySync1CMinuteCommand extends Command
{
    protected $signature = 'inventory:sync-1c-minute';

    protected $description = 'Run minute-based 1C sync for changed products and stocks.';

    public function handle(
        Get1CSyncCursorAction $getCursorAction,
        RunMinute1CSyncAction $runMinute1CSyncAction,
        Store1CSyncCursorAction $storeCursorAction
    ): int {
        Log::info('1C minute sync command: started');

        try {
            $cursor = $getCursorAction->execute();
            $result = $runMinute1CSyncAction->execute($cursor);
            $storedCursor = $storeCursorAction->execute((string) $result['next_cursor']);

            Log::info('1C minute sync command: finished', [
                'run_id' => $result['run_id'] ?? null,
                'changed_after' => $cursor,
                'stored_cursor' => $storedCursor,
                'imported_created' => $result['imported_created'] ?? 0,
                'imported_updated' => $result['imported_updated'] ?? 0,
                'queued' => $result['queued'] ?? 0,
            ]);

            $this->info('1C minute sync completed successfully.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('1C minute sync command: failed', [
                'error_class' => $e::class,
                'error_message' => $e->getMessage(),
            ]);
            $this->error('1C minute sync failed. See logs for details.');

            return self::FAILURE;
        }
    }
}
