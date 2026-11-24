<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneActivityAudit extends Command
{
    protected $signature = 'audit:prune-activity {--limit=5000}';
    protected $description = 'Remove expired activity audit rows in bounded batches';

    public function handle(): int
    {
        if (! config('activity_audit.enabled')) { return self::SUCCESS; }
        $limit = max(1, min(10000, (int) $this->option('limit')));
        foreach (app(\App\Services\Audit\ActivityAuditSettings::class)->get()['retention_days'] as $category => $days) {
            $cutoff = now()->subDays(max(1, (int) $days));
            $ids = DB::table('activity_audit_events')->where('category', $category)
                ->where('created_at', '<', $cutoff)->orderBy('created_at')->orderBy('id')->limit($limit)->pluck('id');
            if ($ids->isNotEmpty()) {
                DB::table('activity_audit_events')->whereIn('id', $ids)->where('created_at', '<', $cutoff)->delete();
            }
        }
        return self::SUCCESS;
    }
}
