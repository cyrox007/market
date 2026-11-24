<?php

namespace App\Console\Commands;

use App\Services\Audit\ActivityAuditHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class CheckActivityAudit extends Command
{
    protected $signature = 'audit:check-activity';
    protected $description = 'Check activity audit backlog and recent failed jobs';

    public function handle(ActivityAuditHealth $health): int
    {
        if (! config('activity_audit.enabled')) { $this->info('Activity audit disabled'); return self::SUCCESS; }
        try {
            $result = $health->inspect();
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
            if (! $result['healthy']) {
                Log::warning('activity_audit_queue_unhealthy', $result);
                return self::FAILURE;
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::warning('activity_audit_health_failed', ['exception_type' => $e::class]);
            $this->error('Activity audit health check failed');
            return self::FAILURE;
        }
    }
}
