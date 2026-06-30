<?php

namespace Database\Seeders;

use App\Models\Product\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ProductGalleryPhotosSeeder extends Seeder
{
    private const GALLERY_PHOTOS_PER_PRODUCT = 3;

    /**
     * Локальные изображения из storage
     */
    private array $availableImages = [];

    /**
     * URL-заглушки для мебели (если нет локальных файлов)
     */
    private array $placeholderUrls = [
        'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&q=80',
        'https://images.unsplash.com/photo-1540518614846-7eded433c457?w=800&q=80',
        'https://images.unsplash.com/photo-1506898667547-42e9a17a9b8d?w=800&q=80',
        'https://images.unsplash.com/photo-1567538096630-e0c55bd6374c?w=800&q=80',
        'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80',
        'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?w=800&q=80',
        'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?w=800&q=80',
        'https://images.unsplash.com/photo-1538688525198-9b88f6f53126?w=800&q=80',
        'https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?w=800&q=80',
    ];

    /**
     * Добавить по 3 фото в галерею каждому товару.
     */
    public function run(): void
    {
        $this->loadAvailableImages();

        $products = Product::all();
        $added = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $currentGalleryCount = $product->getMedia('gallery')->count();
            $toAdd = self::GALLERY_PHOTOS_PER_PRODUCT;

            for ($i = 0; $i < $toAdd; $i++) {
                if ($this->addOnePhotoToGallery($product)) {
                    $added++;
                } else {
                    $skipped++;
                }
            }
        }

        $this->command->info("✅ В галереи товаров добавлено фото: {$added}");
        if ($skipped > 0) {
            $this->command->warn("   Пропущено (ошибка или нет источника): {$skipped}");
        }
    }

    private function loadAvailableImages(): void
    {
        $storagePath = storage_path('app/public');
        if (!is_dir($storagePath)) {
            $this->command->warn('Папка storage/app/public не найдена, будут использованы URL-заглушки.');
            return;
        }

        $files = File::allFiles($storagePath);
        foreach ($files as $file) {
            $ext = strtolower($file->getExtension());
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) && strpos($file->getPathname(), '/conversions/') === false) {
                $this->availableImages[] = $file->getPathname();
            }
        }

        $this->command->info('📸 Доступно локальных изображений: ' . count($this->availableImages));
    }

    private function addOnePhotoToGallery(Product $product): bool
    {
        try {
            if (!empty($this->availableImages)) {
                $path = $this->availableImages[array_rand($this->availableImages)];
                if (file_exists($path)) {
                    $product->addMedia($path)->toMediaCollection('gallery');
                    return true;
                }
            }

            $url = $this->placeholderUrls[array_rand($this->placeholderUrls)];
            $product->addMediaFromUrl($url)->toMediaCollection('gallery');
            return true;
        } catch (\Exception $e) {
            $this->command->warn("   ⚠ Товар #{$product->id} ({$product->name}): {$e->getMessage()}");
            return false;
        }
    }
}
