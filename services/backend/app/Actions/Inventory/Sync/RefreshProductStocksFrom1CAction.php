<?php

declare(strict_types=1);

namespace App\Actions\Inventory\Sync;

use App\Models\Product\Product;
use App\Services\Inventory\Integrations\OneCInventoryApiClient;
use Illuminate\Support\Facades\Log;

class RefreshProductStocksFrom1CAction
{
    public function __construct(
        private readonly OneCInventoryApiClient $client,
        private readonly ApplyOneCStockSnapshotAction $applySnapshot
    ) {
    }

    /**
     * @return array{targets:int,synced:int,skipped:int,errors:int,warehouse_rows:int}
     */
    public function execute(Product $product): array
    {
        $targets = collect([$product]);

        if ($product->isVariable() && ! $product->isVariant()) {
            $targets = $targets->merge($product->variants()->get());
        }

        $result = [
            'targets' => $targets->count(),
            'synced' => 0,
            'skipped' => 0,
            'errors' => 0,
            'warehouse_rows' => 0,
        ];

        foreach ($targets as $target) {
            $externalId = trim((string) ($target->external_id ?? ''));

            if ($externalId === '') {
                $result['skipped']++;
                continue;
            }

            try {
                $rows = $this->client->fetchProductStocks($externalId);
                $applied = $this->applySnapshot->execute($target, $rows);
                $result['synced']++;
                $result['warehouse_rows'] += $applied['warehouse_rows'];
            } catch (\Throwable $e) {
                $result['errors']++;

                Log::warning('1C stock refresh: product failed', [
                    'product_id' => $target->id,
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * @return array{targets:int,synced:int,skipped:int,errors:int,warehouse_rows:int}
     */
    public function executeByExternalId(string $externalId): array
    {
        $externalId = trim($externalId);

        if ($externalId === '') {
            return ['targets' => 0, 'synced' => 0, 'skipped' => 1, 'errors' => 0, 'warehouse_rows' => 0];
        }

        $product = Product::query()->where('external_id', $externalId)->first();

        if ($product === null) {
            return ['targets' => 0, 'synced' => 0, 'skipped' => 1, 'errors' => 0, 'warehouse_rows' => 0];
        }

        try {
            $rows = $this->client->fetchProductStocks($externalId);
            $applied = $this->applySnapshot->execute($product, $rows);

            return [
                'targets' => 1,
                'synced' => 1,
                'skipped' => 0,
                'errors' => 0,
                'warehouse_rows' => $applied['warehouse_rows'],
            ];
        } catch (\Throwable $e) {
            Log::warning('1C stock refresh: external_id failed', [
                'product_id' => $product->id,
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            return ['targets' => 1, 'synced' => 0, 'skipped' => 0, 'errors' => 1, 'warehouse_rows' => 0];
        }
    }
}
