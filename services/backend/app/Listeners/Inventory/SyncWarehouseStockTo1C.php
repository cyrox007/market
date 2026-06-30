<?php

namespace App\Listeners\Inventory;

use App\Actions\Inventory\Sync\SyncStocksTo1CAction;
use App\Events\ProductWarehouseStockChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncWarehouseStockTo1C implements ShouldQueue
{
    public function __construct(
        protected SyncStocksTo1CAction $syncStocksTo1CAction,
    ) {
    }

    public function handle(ProductWarehouseStockChanged $event): void
    {
        if ($event->productExternalId === '' || $event->warehouseExternalId === '') {
            return;
        }

        $this->syncStocksTo1CAction->execute([
            [
                'externalId' => $event->productExternalId,
                'stockId' => $event->warehouseExternalId,
                'count' => (float) $event->quantity,
            ],
        ]);
    }
}

