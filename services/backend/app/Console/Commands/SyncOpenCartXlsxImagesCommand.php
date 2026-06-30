<?php

namespace App\Console\Commands;

use App\Models\Product\Product;
use App\Services\Catalog\Integrations\OpenCart\OpenCartXlsxReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOpenCartXlsxImagesCommand extends Command
{
    protected $signature = 'catalog:sync-opencart-xlsx-images
                            {file : Путь к XLSX (например storage/app/products-2026-03-03-start-52-end-598.xlsx)}
                            {--limit=50 : Сколько товаров обработать за запуск}
                            {--offset=0 : Смещение (пагинация по product_id)}
                            {--main : Синхронизировать главное изображение (по умолчанию да)}
                            {--additional : Синхронизировать доп. изображения (лист AdditionalImages)}';

    protected $description = 'Скачать и прикрепить изображения к товарам из OpenCart XLSX пакетами';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $path = trim($path);
        if ($path === '') {
            $this->error('Укажите путь к XLSX.');
            return self::FAILURE;
        }
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Файл не найден или недоступен: ' . $path);
            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $limit = $limit > 0 ? $limit : 50;
        $offset = (int) $this->option('offset');
        $offset = max(0, $offset);

        $doMain = (bool) $this->option('main');
        $doAdditional = (bool) $this->option('additional');
        if (! $doMain && ! $doAdditional) {
            // по умолчанию только main (чтобы не качать лишнее неожиданно)
            $doMain = true;
        }

        $reader = new OpenCartXlsxReader($path);
        $products = $reader->readSheet('Products');

        // product_id => main url
        $mainByProductId = [];
        foreach ($products as $row) {
            $pid = $row['product_id'] ?? null;
            if ($pid === null || $pid === '') {
                continue;
            }
            $url = $this->normalizeImageUrl($row['image'] ?? null);
            if ($url === null) {
                continue;
            }
            $mainByProductId[(string) $pid] = $url;
        }

        $additionalByProductId = [];
        if ($doAdditional) {
            $additionalRows = $reader->readSheet('AdditionalImages');
            foreach ($additionalRows as $row) {
                $pid = $row['product_id'] ?? null;
                if ($pid === null || $pid === '') {
                    continue;
                }
                $url = $this->normalizeImageUrl($row['image'] ?? null);
                if ($url === null) {
                    continue;
                }
                $additionalByProductId[(string) $pid][] = $url;
            }
        }

        $productIds = array_keys($mainByProductId);
        sort($productIds, SORT_NATURAL);
        $chunk = array_slice($productIds, $offset, $limit);

        $this->info("Файл: {$path}");
        $this->info('Режим: ' . ($doMain ? 'main' : '') . ($doAdditional ? ($doMain ? '+additional' : 'additional') : ''));
        $this->info("Обрабатываю товары: offset={$offset}, limit={$limit}, items=" . count($chunk));

        $ok = 0;
        $missing = 0;
        $failed = 0;

        foreach ($chunk as $externalId) {
            /** @var Product|null $product */
            $product = Product::query()
                ->whereNull('parent_product_id')
                ->where('external_id', (string) $externalId)
                ->first();

            if (! $product) {
                $missing++;
                continue;
            }

            try {
                if ($doMain) {
                    $url = $mainByProductId[(string) $externalId] ?? null;
                    if ($url) {
                        $product->clearMediaCollection('images');
                        $product->addMediaFromUrl($url)->toMediaCollection('images');
                    }
                }

                if ($doAdditional) {
                    $addUrls = $additionalByProductId[(string) $externalId] ?? [];
                    $hasMain = $product->getFirstMediaUrl('images') !== '';
                    if (count($addUrls) > 0 && ! $hasMain) {
                        $product->clearMediaCollection('images');
                        $product->addMediaFromUrl($addUrls[0])->toMediaCollection('images');
                        $addUrls = array_slice($addUrls, 1);
                    }
                    foreach ($addUrls as $url) {
                        $product->addMediaFromUrl($url)->toMediaCollection('gallery');
                    }
                }

                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('OpenCart xlsx image sync failed', [
                    'product_id' => $product->id ?? null,
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Готово: ok={$ok}, missing_in_db={$missing}, failed={$failed}");
        $this->line('Следующий батч: --offset=' . ($offset + $limit));

        return self::SUCCESS;
    }

    private function normalizeImageUrl(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        $base = 'https://svetofor-mebel.ru/image/';
        $url = ltrim($url, '/');
        if (! str_starts_with($url, 'catalog/')) {
            $url = 'catalog/' . $url;
        }
        return $base . $url;
    }
}

