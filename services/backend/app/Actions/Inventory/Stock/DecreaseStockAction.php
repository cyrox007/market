<?php

namespace App\Actions\Inventory\Stock;

use App\Models\Order\Order;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\Log;

class DecreaseStockAction
{
    public function __construct(
        protected StockService $stockService
    ) {
    }

    public function execute(Order $order): array
    {
        Log::info("Decreasing stock for order", [
            'order_id' => $order->id,
            'order_number' => $order->number,
        ]);

        $results = $this->stockService->decreaseStockForOrder($order);

        $successCount = count(array_filter($results, fn($r) => $r['success'] ?? false));
        $totalCount = count($results);

        Log::info("Stock decrease completed", [
            'order_id' => $order->id,
            'successful' => $successCount,
            'total' => $totalCount,
        ]);

        return $results;
    }
}

