<?php

declare(strict_types=1);

namespace App\Jobs\Integration;

use App\Models\Product\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchAllProductStockRefreshFrom1CJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct()
    {
        $this->queue = (string) config(
            'catalog_import.config.svetofor_1c.stock_refresh_queue',
            'integration-1c'
        );
    }

    public function handle(): void
    {
        $queued = 0;

        Product::query()
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->select(['id', 'external_id'])
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$queued): void {
                foreach ($products as $product) {
                    RefreshProductStockFrom1CJob::dispatch((string) $product->external_id);
                    $queued++;
                }
            });

        Log::info('1C stock refresh: global dispatch finished', [
            'queued' => $queued,
        ]);
    }
}
