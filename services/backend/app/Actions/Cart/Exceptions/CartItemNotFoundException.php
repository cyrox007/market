<?php

namespace App\Actions\Cart\Exceptions;

use RuntimeException;

class CartItemNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Товар не найден в корзине');
    }
}
