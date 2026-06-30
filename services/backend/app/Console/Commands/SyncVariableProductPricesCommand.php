<?php

namespace App\Console\Commands;

use App\Models\Product\Product;
use Illuminate\Console\Command;

class SyncVariableProductPricesCommand extends Command
{
    protected $signature = 'products:sync-variable-prices';

    protected $description = 'Установить цену вариативных товаров = минимум цен активных вариаций (для уже существующих записей)';

    public function handle(): int
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Product> $parents */
        $parents = Product::query()
            ->whereNull('parent_product_id')
            ->where('is_variable', true)
            ->get();

        $count = 0;
        foreach ($parents as $parent) {
            /** @var Product $parent */
            $before = $parent->price;
            Product::syncParentPriceFromVariants($parent);
            $parent->refresh();
            if ((float) $before !== (float) $parent->price) {
                $count++;
                $this->line("Product #{$parent->id} ({$parent->slug}): {$before} → {$parent->price}");
            }
        }

        $this->info("Синхронизировано: {$count} из {$parents->count()} вариативных товаров.");
        return self::SUCCESS;
    }
}
