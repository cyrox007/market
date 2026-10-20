<?php

namespace App\Actions\Cart\Exceptions;

use RuntimeException;

class CartProductUnavailableException extends RuntimeException
{
    public function __construct(public readonly ?int $stock)
    {
        parent::__construct('Товар недоступен для заказа');
    }
}
