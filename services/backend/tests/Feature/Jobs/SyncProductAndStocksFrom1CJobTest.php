<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Actions\Inventory\Sync\SyncProductStocksFrom1CByExternalIdAction;
use App\Jobs\Integration\SyncProductAndStocksFrom1CJob;
use Mockery;
use Tests\TestCase;

class SyncProductAndStocksFrom1CJobTest extends TestCase
{
    public function test_it_uses_configured_queue_and_executes_sync_action(): void
    {
        config()->set('catalog_import.config.svetofor_1c.minute_sync_queue', 'integration-1c-minute');
        config()->set('catalog_import.config.svetofor_1c.minute_sync_job_tries', 5);
        config()->set('catalog_import.config.svetofor_1c.minute_sync_job_backoff', 30);
        config()->set('catalog_import.config.svetofor_1c.minute_sync_job_timeout', 90);

        $job = new SyncProductAndStocksFrom1CJob('sku-1');

        $this->assertSame('integration-1c-minute', $job->queue);
        $this->assertSame(5, $job->tries);
        $this->assertSame(30, $job->backoff);
        $this->assertSame(90, $job->timeout);

        $action = Mockery::mock(SyncProductStocksFrom1CByExternalIdAction::class);
        $action->shouldReceive('execute')->once()->with('sku-1')->andReturn(true);

        $job->handle($action);
    }

    public function test_it_handles_domain_skip_without_failing(): void
    {
        $job = new SyncProductAndStocksFrom1CJob('missing-external-id');
        $action = Mockery::mock(SyncProductStocksFrom1CByExternalIdAction::class);
        $action->shouldReceive('execute')->once()->with('missing-external-id')->andReturn(false);

        $job->handle($action);
        $this->assertTrue(true);
    }
}
