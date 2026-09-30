<?php

declare(strict_types=1);

namespace App\Services\Catalog\Integrations\Ozon;

use App\Models\Product\Product;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class OzonProductImageSyncService
{
    private const SOURCE = 'ozon';
    private const MAX_GALLERY_IMAGES = 20;
    private const MAX_IMAGE_BYTES = 15_728_640;

    /**
     * @param list<string> $galleryUrls
     * @return array{downloaded:int, skipped:int, removed:int, errors:list<string>}
     */
    public function sync(Product $product, ?string $mainUrl, array $galleryUrls): array
    {
        $result = ['downloaded' => 0, 'skipped' => 0, 'removed' => 0, 'errors' => []];
        $mainUrl = $this->normalizeUrl($mainUrl);
        $galleryUrls = array_values(array_unique(array_filter(array_map(
            fn (string $url) => $this->normalizeUrl($url),
            array_map('strval', $galleryUrls),
        ))));
        $galleryUrls = array_slice(array_values(array_filter(
            $galleryUrls,
            fn (?string $url) => $url !== null && $url !== $mainUrl,
        )), 0, self::MAX_GALLERY_IMAGES);

        $incoming = array_values(array_filter(array_merge([$mainUrl], $galleryUrls)));
        $this->removeStaleOzonGalleryMedia($product, $incoming, $result);

        if ($mainUrl !== null) {
            $this->syncMainImage($product, $mainUrl, $result);
        }

        foreach ($galleryUrls as $url) {
            if ($this->findBySourceUrl($product, $url) !== null) {
                $result['skipped']++;
                continue;
            }

            try {
                $this->downloadAndAttach($product, $url, 'gallery');
                $result['downloaded']++;
            } catch (\Throwable $e) {
                $result['errors'][] = $url . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    /** @param array{downloaded:int, skipped:int, removed:int, errors:list<string>} $result */
    private function syncMainImage(Product $product, string $url, array &$result): void
    {
        $existingByUrl = $this->findBySourceUrl($product, $url);
        if ($existingByUrl !== null) {
            $result['skipped']++;
            return;
        }

        $currentMain = $product->getFirstMedia('images');
        if ($currentMain !== null && $currentMain->getCustomProperty('source') !== self::SOURCE) {
            try {
                $this->downloadAndAttach($product, $url, 'gallery');
                $result['downloaded']++;
            } catch (\Throwable $e) {
                $result['errors'][] = $url . ': ' . $e->getMessage();
            }
            return;
        }

        if ($currentMain !== null) {
            $currentMain->delete();
            $result['removed']++;
        }

        try {
            $this->downloadAndAttach($product, $url, 'images');
            $result['downloaded']++;
        } catch (\Throwable $e) {
            $result['errors'][] = $url . ': ' . $e->getMessage();
        }
    }

    /** @param array{downloaded:int, skipped:int, removed:int, errors:list<string>} $result */
    private function removeStaleOzonGalleryMedia(Product $product, array $incomingUrls, array &$result): void
    {
        $incomingLookup = array_fill_keys($incomingUrls, true);

        foreach ($product->getMedia('gallery') as $media) {
            if ($media->getCustomProperty('source') !== self::SOURCE) {
                continue;
            }

            $sourceUrl = (string) $media->getCustomProperty('source_url', '');
            if ($sourceUrl === '' || isset($incomingLookup[$sourceUrl])) {
                continue;
            }

            $media->delete();
            $result['removed']++;
        }
    }

    private function findBySourceUrl(Product $product, string $url): ?Media
    {
        $product->unsetRelation('media');

        return $product->getMedia('images')->concat($product->getMedia('gallery'))->first(function (Media $media) use ($url): bool {
            return $media->getCustomProperty('source') === self::SOURCE
                && $media->getCustomProperty('source_url') === $url;
        });
    }

    private function downloadAndAttach(Product $product, string $url, string $collection): Media
    {
        $this->assertAllowedOzonUrl($url);

        $response = $this->http()->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('HTTP ' . $response->status());
        }

        $content = $response->body();
        if ($content === '') {
            throw new RuntimeException('сервер вернул пустой файл');
        }
        if (strlen($content) > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('изображение больше 15 МБ');
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if ($contentType !== '' && ! in_array($contentType, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('неподдерживаемый тип изображения (' . $contentType . ')');
        }

        $extension = $this->extensionFor($url, $contentType);
        $tempPath = tempnam(storage_path('app'), 'ozon-image-');
        if ($tempPath === false) {
            throw new RuntimeException('не удалось создать временный файл');
        }

        $finalTempPath = $tempPath . '.' . $extension;
        @rename($tempPath, $finalTempPath);
        file_put_contents($finalTempPath, $content);

        try {
            return $product->addMedia($finalTempPath)
                ->usingName(pathinfo(parse_url($url, PHP_URL_PATH) ?: 'ozon-image', PATHINFO_FILENAME))
                ->usingFileName(Str::uuid()->toString() . '.' . $extension)
                ->withCustomProperties([
                    'source' => self::SOURCE,
                    'source_url' => $url,
                ])
                ->toMediaCollection($collection);
        } finally {
            if (is_file($finalTempPath)) {
                @unlink($finalTempPath);
            }
        }
    }

    private function http(): PendingRequest
    {
        return Http::timeout(30)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->withOptions([
                'allow_redirects' => false,
                'verify' => (bool) config('catalog_import.config.ozon.image_verify_ssl', true),
            ])
            ->withHeaders(['User-Agent' => 'Svetofor-Mebel/OzonImport']);
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
    }

    private function assertAllowedOzonUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('некорректный URL изображения');
        }

        $allowed = $host === 'ozon.ru'
            || $host === 'ozone.ru'
            || str_ends_with($host, '.ozon.ru')
            || str_ends_with($host, '.ozone.ru');

        if (! $allowed) {
            throw new RuntimeException('домен изображения не относится к Ozon: ' . $host);
        }
    }

    private function extensionFor(string $url, string $contentType): string
    {
        $fromType = match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/jpeg', 'image/jpg' => 'jpg',
            default => null,
        };
        if ($fromType !== null) {
            return $fromType;
        }

        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
            ? ($extension === 'jpeg' ? 'jpg' : $extension)
            : 'jpg';
    }
}
