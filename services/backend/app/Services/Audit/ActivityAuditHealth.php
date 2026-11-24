<?php

namespace App\Services\Audit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final class ActivityAuditHealth
{
    public function inspect(): array
    {
        $connection = (string) config('queue_workers.connection', 'database');
        $driver = config('queue.connections.'.$connection.'.driver');
        $size = Queue::connection($connection)->size('activity-audit');
        $oldest = null;
        if ($driver === 'database') {
            $db = DB::connection(config('queue.connections.'.$connection.'.connection'));
            $created = $db->table(config('queue.connections.'.$connection.'.table', 'jobs'))
                ->where('queue', 'activity-audit')->min('created_at');
            $oldest = $created === null ? 0 : max(0, now()->timestamp - (int) $created);
        }
        $failed = null;
        if (in_array(config('queue.failed.driver'), ['database','database-uuids'], true)) {
            $failedConnection = DB::connection(config('queue.failed.database'));
            $table = config('queue.failed.table', 'failed_jobs');
            if ($failedConnection->getSchemaBuilder()->hasTable($table)) {
                $failed = $failedConnection->table($table)->where('queue', 'activity-audit')
                    ->where('failed_at', '>=', now()->subDay())->count();
            }
        }
        return ['pending' => $size, 'oldest_seconds' => $oldest, 'failed_24h' => $failed,
            'healthy' => $size <= 1000 && ($oldest === null || $oldest <= 300) && ($failed === null || $failed === 0)];
    }
}
