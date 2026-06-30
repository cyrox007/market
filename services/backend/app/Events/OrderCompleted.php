<?php

namespace App\Events;

use App\Models\Order\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие завершения заказа
 * Используется вместо Vanilo\Order\Events\OrderWasCompleted
 * для совместимости с нашей кастомной моделью Order
 */
class OrderCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order
    ) {
    }

    /**
     * Получить заказ (совместимость с Vanilo событиями)
     */
    public function getOrder(): Order
    {
        return $this->order;
    }
}
