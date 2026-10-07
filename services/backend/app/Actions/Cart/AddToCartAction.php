<?php

namespace App\Actions\Cart;

use App\Actions\Cart\Data\CartQuantityResult;
use App\Actions\Cart\Exceptions\CartProductUnavailableException;
use App\Models\Product\Product;
use App\Services\Inventory\StockAvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Vanilo\Cart\Events\CartUpdated;
use Vanilo\Cart\Facades\Cart;

/**
 * Кладёт товар в корзину; для товара, который уже в ней, прибавляет количество к той же позиции.
 */
class AddToCartAction
{
    public function __construct(
        protected CartQuantityPolicy $policy,
        protected StockAvailabilityService $stockAvailability,
    ) {
    }

    /**
     * @throws CartProductUnavailableException
     */
    public function execute(Product $product, int $requested, ?int $shippingLocationId): CartQuantityResult
    {
        $stock = $this->stockAvailability->resolveAvailableStock($product, $shippingLocationId);

        $updatedInPlace = false;
        $result = DB::transaction(function () use ($product, $requested, $stock, &$updatedInPlace): CartQuantityResult {
            // Позицию ищем в базе под блокировкой: снимок корзины в памяти мог устареть
            $item = Cart::model()?->items()->byProduct($product)->lockForUpdate()->first();
            $previous = $item ? (int) $item->quantity : 0;

            $limit = $this->policy->limit($product, $requested, $previous, $stock);
            if ($limit->quantity <= 0) {
                throw new CartProductUnavailableException($stock);
            }

            if ($item) {
                $item->update(['quantity' => $previous + $limit->quantity, 'price' => $product->getPrice()]);
                $updatedInPlace = true;
            } else {
                $item = Cart::addItem($product, $limit->quantity);
            }

            return new CartQuantityResult($item, $requested, $limit->quantity, $previous, $stock, $limit->reason);
        });

        // Менеджер корзины держит загруженные позиции — освежаем после правки строки
        Cart::model()?->load('items');

        // Слушатели Vanilo пересчитывают корзину по CartUpdated — сообщаем о правке строки
        if ($updatedInPlace) {
            Event::dispatch(new CartUpdated(Cart::model()));
        }

        return $result;
    }
}
