<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Предпрогрев «горячего» сегмента кэша: первые страницы, категории, блоки главной.
 * Запускать после деплоя или по расписанию (scheduler).
 */
class WarmHotCacheCommand extends Command
{
    protected $signature = 'cache:warm-hot
                            {--dry-run : Не выполнять запросы, только показать список}';

    protected $description = 'Прогреть кэш для быстрой отдачи: категории, первая страница каталога, featured/new/sale';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $items = [
            'categories.index' => fn () => app(CategoryController::class)->index(new Request()),
            'categories.tree' => fn () => app(CategoryController::class)->tree(new Request()),
            'products.index (page 1)' => fn () => app(ProductController::class)->index(new Request(['page' => 1, 'per_page' => 20])),
            'products.featured' => fn () => app(ProductController::class)->featured(new Request()),
            'products.new' => fn () => app(ProductController::class)->new(new Request()),
            'products.sale' => fn () => app(ProductController::class)->sale(new Request()),
        ];

        if ($dryRun) {
            $this->info('Будут прогреты ключи:');
            foreach (array_keys($items) as $name) {
                $this->line('  - ' . $name);
            }
            return self::SUCCESS;
        }

        foreach ($items as $name => $warm) {
            try {
                $warm();
                $this->line("<info>OK</info> {$name}");
            } catch (\Throwable $e) {
                $this->error("FAIL {$name}: " . $e->getMessage());
            }
        }

        $this->info('Горячий кэш прогрет.');
        return self::SUCCESS;
    }
}
