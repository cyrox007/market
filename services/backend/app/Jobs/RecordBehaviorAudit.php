<?php

namespace App\Jobs;

use App\Services\Audit\ActivityAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RecordBehaviorAudit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 20;

    public function __construct(
        private readonly string $eventId,
        private readonly string $occurredAt,
        private readonly string $event,
        private readonly string $result,
        private readonly int $status,
        private readonly array $context,
    ) {
        $this->onConnection((string) config('queue_workers.connection', 'database'));
        $this->onQueue('activity-audit');
    }

    public function handle(ActivityAudit $audit): void
    {
        if (! in_array($this->event, ['catalog.product_view','catalog.search','catalog.list'], true)) { return; }
        $audit->record('behavior', $this->event, result: $this->result, details: ['http_status' => $this->status],
            context: $this->context, eventId: $this->eventId, occurredAt: $this->occurredAt);
    }
}
