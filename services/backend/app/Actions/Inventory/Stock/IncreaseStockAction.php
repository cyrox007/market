<?php

namespace App\Actions\Inventory\Stock;

use App\Models\Order\Order;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\Log;

class IncreaseStockAction
{
    public function __construct(
        protected StockService $stockService
    ) {
    }

    public function execute(Order $order): array
    {
        Log::info("Increasing stock for cancelled order", [
            'order_id' => $order->id,
            'order_number' => $order->number,
        ]);

        $results = $this->stockService->increaseStockForOrder($order);

        $successCount = count(array_filter($results, fn($r) => $r['success'] ?? false));
        $totalCount = count($results);

        Log::info("Stock increase completed", [
            'order_id' => $order->id,
            'successful' => $successCount,
            'total' => $totalCount,
        ]);

        return $results;
    }
}

