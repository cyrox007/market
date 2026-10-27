<?php
// Standalone integration check. Uses a newly created disposable MySQL database only.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;

Http::preventStrayRequests();
Queue::fake();
config(['cache.default' => 'array', 'services.integration_1c.enabled' => false]);
$worker = ($argv[1] ?? '') === '--worker';
$database = $worker ? $argv[2] : 'svetofor_pilot_audit_'.date('YmdHis');
if (! preg_match('/^svetofor_pilot_audit_\d{14}$/D', $database)) { throw new RuntimeException('Invalid disposable database name'); }
if (! $worker) { DB::connection('mysql')->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); }
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database]);
DB::purge('mysql');
if ($worker) {
    request()->attributes->set('cart_reservation_owner', str_repeat($argv[4], 64));
    $gate = $argv[5];
    file_put_contents($gate.'.'.$argv[4], 'ready');
    $until = microtime(true) + 20;
    while (! file_exists($gate) && microtime(true) < $until) { usleep(10000); }
    if (! file_exists($gate)) { throw new RuntimeException('Barrier timeout'); }
    try {
        app(\App\Services\Inventory\CartStockReservations::class)->reserve(\App\Models\Product\Product::findOrFail((int) $argv[3]), 1);
        echo 'reserved';
    } catch (\Illuminate\Validation\ValidationException $error) { echo 'unavailable'; }
    exit;
}

$gate = sys_get_temp_dir().DIRECTORY_SEPARATOR.$database.'_gate';
$processes = [];
try {
    if (DB::connection()->getDatabaseName() !== $database) { throw new RuntimeException('Isolation check failed'); }
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $sku = \App\Models\Product\Product::factory()->create(['price' => 1000, 'stock' => 1, 'backorder' => false]);
    $warehouse = \App\Models\Inventory\Warehouse::create(['name' => 'Disposable test', 'is_active' => true]);
    $warehouse->productStocks()->create(['product_id' => $sku->id, 'quantity' => 1]);
    foreach (['a', 'b'] as $owner) {
        $process = new Process([PHP_BINARY, __FILE__, '--worker', $database, (string) $sku->id, $owner, $gate], dirname(__DIR__));
        $process->setTimeout(45)->start();
        $processes[] = $process;
    }
    $until = microtime(true) + 25;
    while ((! file_exists($gate.'.a') || ! file_exists($gate.'.b')) && microtime(true) < $until) { usleep(10000); }
    file_put_contents($gate, 'go');
    $results = [];
    foreach ($processes as $process) {
        $process->wait();
        if (! $process->isSuccessful()) { throw new RuntimeException('Worker failed: '.$process->getErrorOutput()); }
        $results[] = trim($process->getOutput());
    }
    sort($results);
    if ($results !== ['reserved', 'unavailable'] || DB::table('cart_stock_reservations')->sum('quantity') != 1) {
        throw new RuntimeException('Last-unit race failed: '.json_encode($results));
    }
    echo "PASS: concurrent buyers, one reserved unit, second buyer rejected.\n";
} finally {
    foreach ($processes as $process) { if ($process->isRunning()) { $process->stop(); } }
    DB::disconnect('mysql');
    config(['database.connections.mysql.database' => 'information_schema']);
    DB::purge('mysql');
    DB::connection('mysql')->statement('DROP DATABASE `'.$database.'`');
    foreach ([$gate, $gate.'.a', $gate.'.b'] as $file) { if (is_file($file)) { unlink($file); } }
}
