<?php

namespace App\Services\Shipping;

use Carbon\CarbonImmutable;

class FulfillmentDates
{
    public function range(int $min, int $max): array
    {
        $today = CarbonImmutable::today('Europe/Moscow');

        return ['days_min' => $min, 'days_max' => $max, 'day_type' => 'calendar', 'timezone' => 'Europe/Moscow',
            'date_min' => $today->addDays($min)->toDateString(), 'date_max' => $today->addDays($max)->toDateString(),
            'label' => $min === $max ? match ($min) {
                0 => 'Сегодня', 1 => 'Завтра', default => null
            } : null];
    }
}
