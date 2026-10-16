<?php

namespace App\Actions\Cart\Data;

use App\Actions\Cart\CartQuantityPolicy;

enum CartLimitReason: string
{
    case Stock = 'stock';
    case MaxPerOrder = 'max_per_order';

    public function message(int $requested, ?int $stock): string
    {
        return match ($this) {
            self::Stock => "запрошено {$requested}, доступно только {$stock} шт. на складе",
            self::MaxPerOrder => "запрошено {$requested}, максимальное количество в одном заказе - " . CartQuantityPolicy::MAX_PER_ORDER . ' шт.',
        };
    }
}
