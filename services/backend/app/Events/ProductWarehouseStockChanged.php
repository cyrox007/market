<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductWarehouseStockChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $stockId,
        public string $productExternalId,
        public string $warehouseExternalId,
        public float $quantity,
        public string $action = 'updated',
    ) {
    }
}

