<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Inventory\Sync\Dispatch1CSyncJobsAction;
use App\Actions\Inventory\Sync\RunMinute1CSyncAction;
use App\Services\Catalog\Integrations\Svetofor1CCatalogImport;
use Tests\TestCase;

class RunMinute1CSyncActionTest extends TestCase
{
    public function test_it_imports_changed_products_and_dispatches_jobs(): void
    {
        $catalogImport = new class extends Svetofor1CCatalogImport
        {
            public function __construct()
            {
            }

            public function importChangedProducts(?string $updatedAfter = null): array
            {
                return [
                    'external_ids' => ['p-1', 'p-2'],
                    'created' => 1,
                    'updated' => 2,
                ];
            }
        };

        $dispatchAction = new class extends Dispatch1CSyncJobsAction
        {
            public function execute(array $externalIds): array
            {
                return [
                    'queued' => count($externalIds),
                    'skipped_duplicates' => 0,
                ];
            }
        };

        $action = new RunMinute1CSyncAction($catalogImport, $dispatchAction);
        $result = $action->execute('2026-04-06T10:00:00.000Z');

        $this->assertSame(1, $result['imported_created']);
        $this->assertSame(2, $result['imported_updated']);
        $this->assertSame(2, $result['changed_external_ids_count']);
        $this->assertSame(2, $result['queued']);
        $this->assertSame(0, $result['skipped_duplicates']);
        $this->assertNotEmpty($result['next_cursor']);
    }
}
