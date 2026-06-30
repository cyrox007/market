<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Actions\Inventory\Sync\Get1CSyncCursorAction;
use App\Actions\Inventory\Sync\RunMinute1CSyncAction;
use App\Actions\Inventory\Sync\Store1CSyncCursorAction;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InventorySync1CMinuteCommandTest extends TestCase
{
    public function test_command_runs_successful_sync_and_returns_success_code(): void
    {
        $getCursor = new class extends Get1CSyncCursorAction
        {
            public function execute(): string
            {
                return '2026-04-06T10:00:00.000Z';
            }
        };

        $runSync = new class extends RunMinute1CSyncAction
        {
            public function __construct()
            {
            }

            public function execute(string $changedAfter): array
            {
                return [
                    'run_id' => 'run-1',
                    'next_cursor' => '2026-04-06T10:01:00.000Z',
                    'imported_created' => 1,
                    'imported_updated' => 2,
                    'queued' => 3,
                ];
            }
        };

        $storeCursor = new class extends Store1CSyncCursorAction
        {
            public function execute(string $nextCursor): string
            {
                return $nextCursor;
            }
        };

        $this->app->instance(Get1CSyncCursorAction::class, $getCursor);
        $this->app->instance(RunMinute1CSyncAction::class, $runSync);
        $this->app->instance(Store1CSyncCursorAction::class, $storeCursor);

        $exitCode = Artisan::call('inventory:sync-1c-minute');
        $this->assertSame(0, $exitCode);
    }

    public function test_command_returns_failure_when_orchestration_throws(): void
    {
        $getCursor = new class extends Get1CSyncCursorAction
        {
            public function execute(): string
            {
                return '2026-04-06T10:00:00.000Z';
            }
        };

        $runSync = new class extends RunMinute1CSyncAction
        {
            public function __construct()
            {
            }

            public function execute(string $changedAfter): array
            {
                throw new \RuntimeException('boom');
            }
        };

        $storeCursor = new class extends Store1CSyncCursorAction
        {
            public function execute(string $nextCursor): string
            {
                return $nextCursor;
            }
        };

        $this->app->instance(Get1CSyncCursorAction::class, $getCursor);
        $this->app->instance(RunMinute1CSyncAction::class, $runSync);
        $this->app->instance(Store1CSyncCursorAction::class, $storeCursor);

        $exitCode = Artisan::call('inventory:sync-1c-minute');
        $this->assertSame(1, $exitCode);
    }
}
