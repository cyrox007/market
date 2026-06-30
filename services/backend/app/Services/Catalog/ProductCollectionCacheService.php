<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product\Product;
use App\Models\Product\ProductCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Инвалидация кэша блоков главной и API подборок после изменений в админке.
 */
class ProductCollectionCacheService
{
    /** Slug подборок, которые отображаются на главной (/products/featured|new|sale). */
    public const HOME_SLUGS = ['featured', 'new', 'sale', 'recommended'];

    public function flushForCollection(?ProductCollection $collection = null): void
    {
        Product::flushAllProductCaches();

        foreach (self::HOME_SLUGS as $slug) {
            Cache::forget(Product::cacheKey('home_collection_' . $slug));
            Cache::forget(Product::cacheKey('collection_slug_' . $slug));
            Cache::forget(Product::cacheKey($slug));
        }

        if ($collection?->slug) {
            Cache::forget(Product::cacheKey('home_collection_' . $collection->slug));
            Cache::forget(Product::cacheKey('collection_slug_' . $collection->slug));
        }

        $this->invalidateFrontendSsrCache();
    }

    private function invalidateFrontendSsrCache(): void
    {
        $url = (string) config('services.frontend.ssr_cache_invalidate_url', '');
        $secret = (string) config('services.frontend.ssr_cache_invalidate_secret', '');

        if ($url === '') {
            return;
        }

        try {
            Http::timeout(3)
                ->withHeaders($secret !== '' ? ['X-Cache-Secret' => $secret] : [])
                ->post($url, [
                    'prefixes' => [
                        '/products/featured',
                        '/products/new',
                        '/products/sale',
                        '/products/collections/',
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('SSR cache invalidate failed: ' . $e->getMessage());
        }
    }
}
