<?php

namespace App\Console\Commands;

use App\Models\Product\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetProductsCommand extends Command
{
    protected $signature = 'products:reset
                            {--seed : Запустить сидеры товаров после очистки}
                            {--force : Не спрашивать подтверждение}';

    protected $description = 'Удалить все товары и связанные данные, при необходимости запустить сидеры заново';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('Удалить все товары и связанные данные?', true)) {
            $this->info('Отменено.');
            return self::SUCCESS;
        }

        $this->info('Очистка товаров и связанных данных...');

        // Порядок важен из-за внешних ключей и связей
        $productIds = Product::pluck('id')->toArray();
        if (empty($productIds)) {
            $this->warn('Товаров в БД нет.');
            return $this->runSeedersIfRequested();
        }

        $this->info('Найдено товаров: ' . count($productIds));

        DB::transaction(function () use ($productIds) {

            // 1. Вариации: атрибуты вариаций
            if (\Schema::hasTable('product_variant_attributes')) {
                DB::table('product_variant_attributes')->whereIn('product_id', $productIds)->delete();
                $this->line('  - product_variant_attributes');
            }

            // 2. Характеристики товаров (product_product_attributes)
            if (\Schema::hasTable('product_product_attributes')) {
                DB::table('product_product_attributes')->whereIn('product_id', $productIds)->delete();
                $this->line('  - product_product_attributes');
            }

            // 3. Сопутствующие товары
            if (\Schema::hasTable('product_related_products')) {
                DB::table('product_related_products')->whereIn('product_id', $productIds)->delete();
                DB::table('product_related_products')->whereIn('related_product_id', $productIds)->delete();
                $this->line('  - product_related_products');
            }

            // 4. Товары в подборках
            if (\Schema::hasTable('product_product_collection')) {
                DB::table('product_product_collection')->whereIn('product_id', $productIds)->delete();
                $this->line('  - product_product_collection');
            }

            // 5. Правила регионов для товаров и вариаций
            if (\Schema::hasTable('product_region_rules')) {
                DB::table('product_region_rules')->whereIn('product_id', $productIds)->delete();
                DB::table('product_region_rules')->whereIn('variant_id', $productIds)->delete();
                $this->line('  - product_region_rules');
            }

            // 6. Связи с блоками фич и доставки
            if (\Schema::hasTable('product_feature_blocks_pivot')) {
                DB::table('product_feature_blocks_pivot')->whereIn('product_id', $productIds)->delete();
                $this->line('  - product_feature_blocks_pivot');
            }
            if (\Schema::hasTable('product_delivery_blocks_pivot')) {
                DB::table('product_delivery_blocks_pivot')->whereIn('product_id', $productIds)->delete();
                $this->line('  - product_delivery_blocks_pivot');
            }

            // 7. Точки на идеях интерьера
            if (\Schema::hasTable('interior_idea_hotspots')) {
                DB::table('interior_idea_hotspots')->whereIn('product_id', $productIds)->delete();
                $this->line('  - interior_idea_hotspots');
            }

            // 8. Отзывы
            if (\Schema::hasTable('reviews')) {
                DB::table('reviews')->whereIn('product_id', $productIds)->delete();
                $this->line('  - reviews');
            }

            // 9. Медиа (Spatie Media Library)
            if (\Schema::hasTable('media')) {
                DB::table('media')
                    ->where('model_type', Product::class)
                    ->whereIn('model_id', $productIds)
                    ->delete();
                $this->line('  - media');
            }

            // 10. Категории (taxons) — pivot model_taxons
            if (\Schema::hasTable('model_taxons')) {
                DB::table('model_taxons')
                    ->where('model_type', Product::class)
                    ->whereIn('model_id', $productIds)
                    ->delete();
                $this->line('  - model_taxons');
            }

            // 11. Элементы корзины (если таблица и колонка существуют)
            if (\Schema::hasTable('cart_items')) {
                if (\Schema::hasColumn('cart_items', 'buyable_type')) {
                    $deleted = DB::table('cart_items')
                        ->where('buyable_type', Product::class)
                        ->whereIn('buyable_id', $productIds)
                        ->delete();
                } elseif (\Schema::hasColumn('cart_items', 'product_id')) {
                    $deleted = DB::table('cart_items')->whereIn('product_id', $productIds)->delete();
                } else {
                    $deleted = 0;
                }
                $this->line('  - cart_items: ' . $deleted . ' записей');
            }

            // 12. Удаляем сами товары: сначала вариации, потом родительские
            Product::whereNotNull('parent_product_id')->whereIn('id', $productIds)->delete();
            Product::whereNull('parent_product_id')->delete();
            $this->line('  - products');
        });

        $this->info('Товары и связанные данные удалены.');

        return $this->runSeedersIfRequested();
    }

    private function runSeedersIfRequested(): int
    {
        if (!$this->option('seed')) {
            $this->info('Для заполнения данными выполните: php artisan db:seed --class=FurnitureCatalogSeeder && php artisan db:seed --class=ProductAttributesSeeder && php artisan db:seed --class=ProductVariantsSeeder');
            return self::SUCCESS;
        }

        $this->info('Запуск сидеров...');

        $this->call('db:seed', [
            '--class' => 'FurnitureCatalogSeeder',
        ]);
        $this->call('db:seed', [
            '--class' => 'ProductAttributesSeeder',
        ]);
        $this->call('db:seed', [
            '--class' => 'ProductVariantsSeeder',
        ]);
        $this->call('db:seed', [
            '--class' => 'CategoryProductsSeeder',
        ]);
        $this->call('db:seed', [
            '--class' => 'ProductGalleryPhotosSeeder',
        ]);
        $this->call('db:seed', [
            '--class' => 'ProductCollectionSeeder',
        ]);
        $this->call('db:seed', [
            '--class' => 'ProductBlocksSeeder',
        ]);

        $this->info('Сидеры выполнены. Товары созданы заново.');
        return self::SUCCESS;
    }
}
