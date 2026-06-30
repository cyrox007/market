<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('cache_segments.warm_schedule', true)) {
    Schedule::command('cache:warm-hot')->hourly()->withoutOverlapping(30);
}

Schedule::command('orders:cancel-expired-unpaid')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('inventory:sync-1c-minute')
    ->everyMinute()
    ->withoutOverlapping(1);

// Обработка очередей (default, integration-1c) — короткий запуск раз в минуту через cron + schedule:run
// connection — позиционный аргумент queue:work (не --connection, см. artisan queue:work --help)
Schedule::command('queue:work', array_merge(
    [config('queue_workers.connection', 'database')],
    [
        '--queue' => implode(',', config('queue_workers.queues', ['default', 'integration-1c'])),
        '--sleep' => 1,
        '--tries' => (string) config('queue_workers.tries', 3),
        '--max-time' => (string) config('queue_workers.max_time', 55),
        '--memory' => (string) config('queue_workers.memory', 256),
        '--timeout' => (string) config('queue_workers.timeout', 120),
    ]
))
    ->everyMinute()
    ->withoutOverlapping((int) config('queue_workers.max_time', 55))
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/queue-worker.log'));
