<?php

namespace App\Jobs;

use App\Models\Product\Product;
use App\Services\Catalog\Integrations\Ozon\OzonProductImageSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated Используется для совместимости со старыми вызовами. Новые импорты ставят SyncOzonProductImagesJob.
 */
class ProcessProductImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 3;

    public function __construct(
        protected string $externalId,
        protected ?string $mainPhotoUrl,
        protected array $additionalPhotoUrls,
    ) {
    }

    public function handle(OzonProductImageSyncService $service): void
    {
        $product = Product::query()->where('external_id', $this->externalId)->first();
        if ($product === null) {
            Log::warning('Ozon image sync: product not found', ['external_id' => $this->externalId]);
            return;
        }

        $service->sync($product, $this->mainPhotoUrl, $this->additionalPhotoUrls);
    }
}
