<?php

namespace App\Actions\Cart;

use App\Actions\Cart\Data\CartLimitReason;
use App\Actions\Cart\Data\CartQuantityLimit;
use App\Models\Product\Product;

/**
 * Сколько единиц товара можно положить в позицию с учётом остатка и лимита на заказ, и что ограничило.
 */
class CartQuantityPolicy
{
    public const MAX_PER_ORDER = 100;

    public function limit(Product $product, int $requested, int $alreadyInCart, ?int $stock): CartQuantityLimit
    {
        $byOrderLimit = max(0, self::MAX_PER_ORDER - $alreadyInCart);
        $byStock = ($stock === null || $product->backorder) ? null : max(0, $stock - $alreadyInCart);

        $allowed = $byStock === null ? $byOrderLimit : min($byOrderLimit, $byStock);
        $quantity = min($requested, $allowed);

        if ($quantity >= $requested) {
            return new CartQuantityLimit($quantity, null);
        }

        $reason = ($byStock !== null && $byStock < $byOrderLimit) ? CartLimitReason::Stock : CartLimitReason::MaxPerOrder;

        return new CartQuantityLimit($quantity, $reason);
    }
}
