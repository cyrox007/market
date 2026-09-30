<?php

declare(strict_types=1);

namespace App\Actions\Inventory\Sync;

use App\Models\Product\Product;
use App\Services\Inventory\Integrations\OneCInventoryApiClient;
use Illuminate\Support\Facades\Log;

class RefreshAllStocksFrom1CAction
{
    public function __construct(
        private readonly OneCInventoryApiClient $client,
        private readonly ApplyOneCStockSnapshotAction $applySnapshot
    ) {
    }

    /**
     * @return array{targets:int,synced:int,skipped:int,errors:int,warehouse_rows:int}
     */
    public function execute(): array
    {
        $groups = $this->client->fetchBulkStocks();

        $result = [
            'targets' => count($groups),
            'synced' => 0,
            'skipped' => 0,
            'errors' => 0,
            'warehouse_rows' => 0,
        ];

        if ($groups === []) {
            return $result;
        }

        $products = Product::query()
            ->whereIn('external_id', array_keys($groups))
            ->get()
            ->keyBy(fn (Product $product): string => (string) $product->external_id);

        foreach ($groups as $externalId => $group) {
            /** @var Product|null $product */
            $product = $products->get($externalId);

            if ($product === null) {
                $result['skipped']++;
                continue;
            }

            try {
                $applied = $this->applySnapshot->execute(
                    $product,
                    $group['stocks'],
                    $group['aggregate']
                );
                $result['synced']++;
                $result['warehouse_rows'] += $applied['warehouse_rows'];
            } catch (\Throwable $e) {
                $result['errors']++;

                Log::warning('1C stock refresh: bulk row failed', [
                    'product_id' => $product->id,
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }
}
