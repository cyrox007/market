<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Inventory\Sync\RefreshProductStocksFrom1CAction;
use App\Jobs\Integration\DispatchAllProductStockRefreshFrom1CJob;
use App\Models\Product\Product;
use Illuminate\Console\Command;

class InventoryRefresh1CStocksCommand extends Command
{
    protected $signature = 'inventory:refresh-1c-stocks
        {--product-id= : Обновить остатки конкретного локального товара}
        {--external-id= : Обновить остатки конкретной позиции по external_id}';

    protected $description = 'Обновить только остатки из 1С, не меняя карточки товаров.';

    public function handle(RefreshProductStocksFrom1CAction $action): int
    {
        $productId = trim((string) $this->option('product-id'));
        $externalId = trim((string) $this->option('external-id'));

        if ($productId !== '' && $externalId !== '') {
            $this->error('Используйте только один параметр: --product-id или --external-id.');

            return self::INVALID;
        }

        if ($productId === '' && $externalId === '') {
            DispatchAllProductStockRefreshFrom1CJob::dispatch();

            $this->info('Обновление всех остатков поставлено в очередь integration-1c.');

            return self::SUCCESS;
        }

        if ($productId !== '') {
            $product = Product::query()->find($productId);

            if ($product === null) {
                $this->error("Товар {$productId} не найден.");

                return self::FAILURE;
            }

            $result = $action->execute($product);
        } else {
            $result = $action->executeByExternalId($externalId);
        }

        $this->table(
            ['Целей', 'Обновлено', 'Пропущено', 'Ошибок', 'Складских строк'],
            [[
                $result['targets'],
                $result['synced'],
                $result['skipped'],
                $result['errors'],
                $result['warehouse_rows'],
            ]]
        );

        foreach ($result['error_messages'] as $error) {
            $this->error($error);
        }

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
