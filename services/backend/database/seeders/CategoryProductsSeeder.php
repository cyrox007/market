<?php

namespace Database\Seeders;

use App\Models\Product\Category;
use App\Models\Product\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Vanilo\Category\Models\Taxonomy;

/**
 * Дополняет категории товарами на основе существующих (с фото).
 * В каждой категории — по 20 товаров для проверки пагинации.
 */
class CategoryProductsSeeder extends Seeder
{
    private const PRODUCTS_PER_CATEGORY = 20;

    public function run(): void
    {
        $taxonomy = Taxonomy::where('slug', 'product-categories')->first();
        if (!$taxonomy) {
            $this->command->warn('Taxonomy product-categories не найдена. Сначала выполните FurnitureCatalogSeeder.');
            return;
        }

        $categories = Category::where('taxonomy_id', $taxonomy->id)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($categories->isEmpty()) {
            $this->command->warn('Категории не найдены.');
            return;
        }

        // Товары с медиа (включая вариации — у них свои фото) для клонирования
        $productsWithMedia = Product::query()
            ->where('state', 'active')
            ->whereHas('media')
            ->with('media')
            ->get();

        if ($productsWithMedia->isEmpty()) {
            $this->command->warn('Нет активных товаров с фото. Сначала выполните FurnitureCatalogSeeder и ProductVariantsSeeder.');
            return;
        }

        $pool = $productsWithMedia->values()->all();
        $poolIndex = 0;
        $created = 0;

        foreach ($categories as $category) {
            $currentCount = $category->products()->count();
            $need = self::PRODUCTS_PER_CATEGORY - $currentCount;

            if ($need <= 0) {
                $this->command->info("  [{$category->slug}] уже {$currentCount} товаров, пропуск.");
                continue;
            }

            for ($i = 0; $i < $need; $i++) {
                $source = $pool[$poolIndex % count($pool)];
                $poolIndex++;

                $clone = $this->cloneProductForCategory($source, $category, $currentCount + $i + 1);
                if ($clone) {
                    $clone->taxons()->sync([$category->id]);
                    $created++;
                }
            }

            $count = Product::whereHas('taxons', fn ($q) => $q->where('taxons.id', $category->id))->count();
            $this->command->info("  [{$category->slug}] товаров: {$count}");
        }

        $this->command->info('✅ CategoryProductsSeeder: добавлено товаров в категории: ' . $created);
    }

    private function cloneProductForCategory(Product $source, Category $category, int $variantIndex): ?Product
    {
        $baseName = preg_replace('/\s*\([^)]*\)\s*$/', '', $source->name);
        $suffix = " ({$category->name}, вариант {$variantIndex})";
        $name = $baseName . $suffix;
        $slugBase = Str::slug($baseName) . '-' . $category->slug . '-' . $variantIndex;
        $slug = $slugBase;
        $attempt = 0;
        while (Product::where('slug', $slug)->exists()) {
            $attempt++;
            $slug = $slugBase . '-' . $attempt;
        }
        $sku = 'SV-CAT-' . strtoupper(Str::slug($category->slug, '')) . '-' . $variantIndex;
        if (Product::where('sku', $sku)->exists()) {
            $sku = $sku . '-' . uniqid();
        }

        $clone = Product::create([
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'price' => $source->price,
            'original_price' => $source->original_price,
            'state' => 'active',
            'description' => $source->description,
            'excerpt' => $source->excerpt,
            'stock' => max(1, (int) $source->stock),
            'backorder' => (bool) $source->backorder,
            'units_sold' => 0,
            'is_variable' => false,
            'parent_product_id' => null,
        ]);

        $this->copyMediaFromProduct($source, $clone);

        return $clone;
    }

    private function copyMediaFromProduct(Product $source, Product $target): void
    {
        $allMedia = $source->getMedia();
        if ($allMedia->isEmpty()) {
            return;
        }

        $first = true;
        foreach ($allMedia as $media) {
            try {
                // У целевого товара коллекция images — singleFile, поэтому первое фото в images, остальные в gallery
                $collection = $first ? 'images' : 'gallery';
                $media->copy($target, $collection);
                $first = false;
            } catch (\Throwable $e) {
                $this->command->warn("    Копирование медиа для {$target->name}: " . $e->getMessage());
            }
        }
    }
}
