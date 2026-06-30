<?php

namespace App\Actions\Inventory\Validation;

use App\Services\Inventory\StockService;
use Vanilo\Cart\Facades\Cart;

class ValidateStockAction
{
    public function __construct(
        protected StockService $stockService
    ) {
    }

    public function execute(?int $shippingLocationId = null): array
    {
        $cartItems = Cart::getItems();

        return $this->stockService->validateStock($cartItems, $shippingLocationId);
    }

    public function executeForItems($items, ?int $shippingLocationId = null): array
    {
        return $this->stockService->validateStock($items, $shippingLocationId);
    }
}

