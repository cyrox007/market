<?php

namespace App\Services\Catalog\Integrations;

use App\Jobs\SyncOzonProductImagesJob;
use App\Models\Product\Product;
use App\Services\Catalog\Integrations\Ozon\OzonXlsxReader;
use Illuminate\Support\Facades\Log;

class ProductImagesImporter
{
    public function import(string $filePath): void
    {
        $parsed = app(OzonXlsxReader::class)->read($filePath);
        $queued = 0;
        $missing = 0;

        foreach ($parsed['rows'] as $row) {
            $mainPhoto = trim((string) ($row['main_image_url'] ?? ''));
            $additional = $row['gallery_urls'] ?? [];
            if ($mainPhoto === '' && $additional === []) {
                continue;
            }

            $externalId = (string) $row['external_id'];
            $product = Product::query()->where('external_id', $externalId)->first();
            if ($product === null) {
                $missing++;
                Log::warning('Ozon image import: product not found', ['external_id' => $externalId]);
                continue;
            }

            SyncOzonProductImagesJob::dispatch(
                (int) $product->id,
                $mainPhoto !== '' ? $mainPhoto : null,
                array_values($additional),
            );
            $queued++;
        }

        Log::info('Ozon image import: queued', [
            'queued' => $queued,
            'missing_products' => $missing,
        ]);
    }
}
