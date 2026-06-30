<?php

namespace App\Actions\Inventory;

use App\Services\Inventory\StockService;
use Vanilo\Cart\Facades\Cart;

/**
 * Действие для проверки остатков перед созданием заказа
 */
class ValidateStockAction
{
    public function __construct(
        protected StockService $stockService
    ) {
    }

    /**
     * Проверить остатки для текущей корзины
     */
    public function execute(): array
    {
        $cartItems = Cart::getItems(); // Получаем коллекцию объектов, не массив
        return $this->stockService->validateStock($cartItems);
    }

    /**
     * Проверить остатки для конкретных элементов
     */
    public function executeForItems($items): array
    {
        return $this->stockService->validateStock($items);
    }
}
