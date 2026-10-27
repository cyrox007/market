<?php

namespace App\Models\Order;

enum OrderStatus: string
{
    case NEW = 'new';
    case MANAGER_PROCESSING = 'manager_processing';
    case ASSEMBLING = 'assembling';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case ACCEPTED = 'accepted';
    case ASSEMBLED = 'assembled';
    case SHIPPED = 'shipped';
    case IN_TRANSIT = 'in_transit';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    /**
     * Get human-readable label for status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Новый',
            self::MANAGER_PROCESSING => 'В обработке у менеджера',
            self::ASSEMBLING => 'Собирается',
            self::AWAITING_PAYMENT => 'Ожидание оплаты',
            self::ACCEPTED => 'Принят',
            self::ASSEMBLED => 'Собран',
            self::SHIPPED => 'Отправлен',
            self::IN_TRANSIT => 'В пути',
            self::DELIVERED => 'Доставлен',
            self::CANCELLED => 'Отменен',
        };
    }

    /**
     * Get all statuses as array for selects.
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) { $options[$case->value] = $case->label(); }
        return $options;
    }
}
