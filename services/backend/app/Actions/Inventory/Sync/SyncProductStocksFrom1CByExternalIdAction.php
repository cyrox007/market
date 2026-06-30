<?php

declare(strict_types=1);

namespace App\Actions\Inventory\Sync;

use App\Services\Catalog\Integrations\Svetofor1CCatalogImport;
use Illuminate\Support\Facades\Log;

class SyncProductStocksFrom1CByExternalIdAction
{
    public function __construct(
        private readonly Svetofor1CCatalogImport $catalogImport
    ) {
    }

    public function execute(string $externalId): bool
    {
        Log::info('1C minute sync: product stock sync started', [
            'external_id' => $externalId,
        ]);

        $stocksSynced = $this->catalogImport->syncStocksByExternalId($externalId);
        $priceSynced = $this->catalogImport->syncPriceByExternalId($externalId);

        if (! $stocksSynced && ! $priceSynced) {
            Log::warning('1C minute sync: product stock sync skipped', [
                'external_id' => $externalId,
            ]);
        }

        Log::info('1C minute sync: product stock sync finished', [
            'external_id' => $externalId,
            'stocks_synced' => $stocksSynced,
            'price_synced' => $priceSynced,
            'synced' => $stocksSynced || $priceSynced,
        ]);

        return $stocksSynced || $priceSynced;
    }
}
