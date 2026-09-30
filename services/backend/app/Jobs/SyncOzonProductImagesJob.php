<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product\Product;
use App\Services\Catalog\Integrations\Ozon\OzonProductImageSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SyncOzonProductImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 3;
    public int $backoff = 30;

    /** @param list<string> $galleryUrls */
    public function __construct(
        public readonly int $productId,
        public readonly ?string $mainUrl,
        public readonly array $galleryUrls = [],
    ) {
        $this->onQueue(config('catalog_import.queue', 'default'));
    }

    public function handle(OzonProductImageSyncService $service): void
    {
        $product = Product::query()->find($this->productId);
        if ($product === null) {
            Log::warning('Ozon image sync: product not found', ['product_id' => $this->productId]);
            return;
        }

        $result = $service->sync($product, $this->mainUrl, $this->galleryUrls);

        Log::info('Ozon image sync: completed', [
            'product_id' => $product->id,
            'external_id' => $product->external_id,
            'result' => $result,
        ]);
    }
}
