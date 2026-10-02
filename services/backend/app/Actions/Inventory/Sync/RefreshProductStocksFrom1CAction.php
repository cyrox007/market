<?php

declare(strict_types=1);

namespace App\Actions\Inventory\Sync;

use App\Models\Product\Product;
use App\Services\Inventory\Integrations\OneCInventoryStockClient;
use Illuminate\Support\Facades\Log;

class RefreshProductStocksFrom1CAction
{
    public function __construct(
        private readonly OneCInventoryStockClient $client,
        private readonly ApplyOneCStockSnapshotAction $applySnapshot
    ) {
    }

    /**
     * Обновить остатки товара. Для вариативного корневого товара обновляются
     * также его торговые предложения.
     *
     * @return array{
     *   targets:int,
     *   synced:int,
     *   skipped:int,
     *   errors:int,
     *   warehouse_rows:int,
     *   error_messages:list<string>
     * }
     */
    public function execute(Product $product): array
    {
        $targets = collect([$product]);

        if ($product->isVariable() && ! $product->isVariant()) {
            $targets = $targets->merge($product->variants()->get());
        }

        $result = $this->emptyResult();
        $result['targets'] = $targets->count();

        foreach ($targets as $target) {
            $externalId = trim((string) ($target->external_id ?? ''));

            if ($externalId === '') {
                $result['skipped']++;
                continue;
            }

            $this->refreshOne($target, $externalId, $result);
        }

        return $result;
    }

    /**
     * Обновить ровно одну локальную позицию по external_id.
     *
     * @return array{
     *   targets:int,
     *   synced:int,
     *   skipped:int,
     *   errors:int,
     *   warehouse_rows:int,
     *   error_messages:list<string>
     * }
     */
    public function executeByExternalId(string $externalId): array
    {
        $externalId = trim($externalId);

        if ($externalId === '') {
            $result = $this->emptyResult();
            $result['skipped'] = 1;

            return $result;
        }

        $product = Product::query()
            ->where('external_id', $externalId)
            ->first();

        if ($product === null) {
            $result = $this->emptyResult();
            $result['skipped'] = 1;

            Log::notice('1C stock refresh: local product not found', [
                'external_id' => $externalId,
            ]);

            return $result;
        }

        $result = $this->emptyResult();
        $result['targets'] = 1;

        $this->refreshOne($product, $externalId, $result);

        return $result;
    }

    /**
     * @param array{
     *   targets:int,
     *   synced:int,
     *   skipped:int,
     *   errors:int,
     *   warehouse_rows:int,
     *   error_messages:list<string>
     * } $result
     */
    private function refreshOne(Product $product, string $externalId, array &$result): void
    {
        try {
            $rows = $this->client->fetchProductStocks($externalId);
            $applied = $this->applySnapshot->execute($product, $rows);

            $result['synced']++;
            $result['warehouse_rows'] += $applied['warehouse_rows'];

            Log::info('1C stock refresh: product updated', [
                'product_id' => $product->id,
                'external_id' => $externalId,
                'warehouse_rows' => $applied['warehouse_rows'],
                'total_stock' => $applied['total_stock'],
            ]);
        } catch (\Throwable $e) {
            $result['errors']++;
            $result['error_messages'][] = $e->getMessage();

            Log::warning('1C stock refresh: product failed', [
                'product_id' => $product->id,
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{
     *   targets:int,
     *   synced:int,
     *   skipped:int,
     *   errors:int,
     *   warehouse_rows:int,
     *   error_messages:list<string>
     * }
     */
    private function emptyResult(): array
    {
        return [
            'targets' => 0,
            'synced' => 0,
            'skipped' => 0,
            'errors' => 0,
            'warehouse_rows' => 0,
            'error_messages' => [],
        ];
    }
}
