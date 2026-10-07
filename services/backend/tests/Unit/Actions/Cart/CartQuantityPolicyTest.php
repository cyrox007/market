<?php

namespace Tests\Unit\Actions\Cart;

use App\Actions\Cart\CartQuantityPolicy;
use App\Actions\Cart\Data\CartLimitReason;
use App\Models\Product\Product;
use Tests\TestCase;

class CartQuantityPolicyTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: int, 2: ?int, 3: bool, 4: int, 5: ?CartLimitReason}>
     */
    public static function cases(): array
    {
        return [
            'хватает остатка' => [3, 0, 10, false, 3, null],
            'урезано по остатку' => [8, 0, 5, false, 5, CartLimitReason::Stock],
            'остаток с учётом уже лежащего' => [4, 3, 5, false, 2, CartLimitReason::Stock],
            'лимит на заказ' => [150, 0, null, false, 100, CartLimitReason::MaxPerOrder],
            'лимит с учётом уже лежащего' => [20, 90, 500, false, 10, CartLimitReason::MaxPerOrder],
            'предзаказ игнорирует остаток' => [7, 0, 0, true, 7, null],
            'остаток исчерпан' => [1, 5, 5, false, 0, CartLimitReason::Stock],
            'остаток и лимит совпали' => [30, 90, 100, false, 10, CartLimitReason::MaxPerOrder],
        ];
    }

    /**
     * @dataProvider cases
     */
    public function test_limit(int $requested, int $inCart, ?int $stock, bool $backorder, int $quantity, ?CartLimitReason $reason): void
    {
        $product = new Product(['backorder' => $backorder]);

        $limit = (new CartQuantityPolicy())->limit($product, $requested, $inCart, $stock);

        $this->assertSame($quantity, $limit->quantity);
        $this->assertSame($reason, $limit->reason);
    }
}
