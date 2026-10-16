<?php

namespace App\Actions\Cart\Data;

final class CartQuantityLimit
{
    public function __construct(
        public readonly int $quantity,
        public readonly ?CartLimitReason $reason,
    ) {
    }
}
