<?php

namespace App\Observers;

use App\Events\ProductWarehouseStockChanged;
use App\Models\Inventory\ProductWarehouseStock;

class ProductWarehouseStockObserver
{
    public function created(ProductWarehouseStock $stock): void
    {
        $this->dispatchChanged($stock, 'created');
    }

    public function updated(ProductWarehouseStock $stock): void
    {
        if ($stock->wasChanged(['quantity', 'warehouse_id', 'product_id'])) {
            $this->dispatchChanged($stock, 'updated');
        }
    }

    public function deleted(ProductWarehouseStock $stock): void
    {
        $this->dispatchChanged($stock, 'deleted');
    }

    private function dispatchChanged(ProductWarehouseStock $stock, string $action): void
    {
        $stock->loadMissing(['product', 'warehouse']);

        $productExternalId = (string) ($stock->product?->external_id ?? '');
        $warehouseExternalId = (string) ($stock->warehouse?->external_id ?? '');
        if ($productExternalId === '' || $warehouseExternalId === '') {
            return;
        }

        event(new ProductWarehouseStockChanged(
            stockId: (int) $stock->id,
            productExternalId: $productExternalId,
            warehouseExternalId: $warehouseExternalId,
            quantity: $action === 'deleted' ? 0.0 : (float) $stock->quantity,
            action: $action,
        ));
    }
}

