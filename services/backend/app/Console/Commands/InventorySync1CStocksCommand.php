<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Inventory\Sync\RefreshAllStocksFrom1CAction;
use App\Actions\Inventory\Sync\RefreshProductStocksFrom1CAction;
use App\Models\Product\Product;
use Illuminate\Console\Command;

class InventorySync1CStocksCommand extends Command
{
    protected $signature = 'inventory:sync-1c-stocks
        {--product-id= : Обновить остатки конкретного локального товара}
        {--external-id= : Обновить остатки конкретной позиции по external_id}';

    protected $description = 'Stock-only sync from 1C. Does not import or modify catalog content.';

    public function handle(
        RefreshProductStocksFrom1CAction $refreshProductStocks,
        RefreshAllStocksFrom1CAction $refreshAllStocks
    ): int {

        $productId = trim((string) $this->option('product-id'));
        $externalId = trim((string) $this->option('external-id'));

        if ($productId !== '' && $externalId !== '') {
            $this->error('Используйте только один из параметров: --product-id или --external-id.');

            return self::INVALID;
        }

        if ($productId !== '') {
            $product = Product::query()->find($productId);

            if ($product === null) {
                $this->error("Товар {$productId} не найден.");

                return self::FAILURE;
            }

            $result = $refreshProductStocks->execute($product);
        } elseif ($externalId !== '') {
            $result = $refreshProductStocks->executeByExternalId($externalId);
        } else {
            $result = $refreshAllStocks->execute();
        }

        $this->table(
            ['Целей', 'Обновлено', 'Пропущено', 'Ошибок', 'Строк складов'],
            [[
                $result['targets'],
                $result['synced'],
                $result['skipped'],
                $result['errors'],
                $result['warehouse_rows'],
            ]]
        );

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
