<?php

// Run only against an isolated test schema; no external integrations, no production data.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ActivityAuditEvent;
use App\Services\Audit\ActivityAudit;
use App\Services\Audit\ActivityAuditHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$db = DB::connection();
if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'activity_audit_ci'
    || ! in_array($db->getConfig('host'), ['127.0.0.1','localhost'], true)) {
    throw new RuntimeException('Only the isolated activity_audit_ci MySQL database is allowed');
}
config(['activity_audit.enabled'=>true,'cache.default'=>'array']);
$check = static function (bool $ok, string $message): void { if (! $ok) { throw new RuntimeException($message); } };
$run = (string) Str::uuid();
$actor = random_int(1000000,2000000000);
$context = ['actor_type'=>'user','source'=>'storefront','actor_id'=>$actor,'session_tag'=>hash('sha256',$run),'request_id'=>$run];
$db->beginTransaction();
try {
    $start = hrtime(true);
    $audit = app(ActivityAudit::class);
    for ($i=0;$i<5000;$i++) {
        $audit->record('behavior','test.mysql_workload',details:['http_status'=>200],context:$context);
    }
    $writeMs = (hrtime(true)-$start)/1e6;
    $start = hrtime(true);
    $query = ActivityAuditHistory::forUser(ActivityAuditEvent::query(),$actor)->where('request_id',$run);
    $check((clone $query)->count()===5000,'Wrong audit row count');
    $page = $query->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(50);
    $check(count($page)===50 && $page->hasMorePages(),'Timeline pagination failed');
    $readMs = (hrtime(true)-$start)/1e6;
    $eventId = (string) Str::uuid();
    $audit->record('behavior','test.mysql_idempotence',context:$context,eventId:$eventId);
    $audit->record('behavior','test.mysql_idempotence',context:$context,eventId:$eventId);
    $check(ActivityAuditEvent::where('event_id',$eventId)->count()===1,'Duplicate event');
    echo json_encode(['status'=>'passed','events'=>5000,'write_ms'=>round($writeMs,2),'timeline_ms'=>round($readMs,2),'peak_memory_bytes'=>memory_get_peak_usage(true)],JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $db->rollBack();
}
