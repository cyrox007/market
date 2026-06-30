<?php

namespace Database\Seeders;

use App\Models\Product\Product;
use Illuminate\Database\Seeder;

class ProductRelatedSeeder extends Seeder
{
    /**
     * Создает для каждого основного товара (parent_product_id = null)
     * до 4 случайных сопутствующих товаров.
     *
     * ВАЖНО:
     * - Связи делаются двусторонними через Product::attachRelatedProduct().
     * - Если у товара уже есть сопутствующие, они НЕ трогаются.
     * - Используется только для дев/демо-данных, а не для продакшена.
     *
     * Запуск:
     * php artisan db:seed --class=ProductRelatedSeeder
     */
    public function run(): void
    {
        $products = Product::whereNull('parent_product_id')
            ->active()
            ->get();

        if ($products->count() < 2) {
            $this->command?->warn('Недостаточно товаров для генерации сопутствующих (нужно минимум 2).');
            return;
        }

        $this->command?->info("Найдено основных товаров: {$products->count()}");

        $relatedCount = 0;

        foreach ($products as $product) {
            // Если у товара уже есть сопутствующие — пропускаем, чтобы не затирать ручные связи
            if ($product->relatedProducts()->exists()) {
                continue;
            }

            // Берем до 4 случайных других товаров
            $candidates = $products
                ->where('id', '!=', $product->id)
                ->shuffle()
                ->take(4);

            if ($candidates->isEmpty()) {
                continue;
            }

            foreach ($candidates as $candidate) {
                // attachRelatedProduct создаёт связь в обе стороны и сбрасывает кэш
                $product->attachRelatedProduct($candidate);
                $relatedCount++;
            }
        }

        $this->command?->info("Создано сопутствующих связей (логических пар A↔B): примерно {$relatedCount}");
    }
}

