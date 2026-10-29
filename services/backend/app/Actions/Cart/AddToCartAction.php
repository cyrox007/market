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
        $stock = $this->stockAvailability->resolveAvailableStock($product);

        $updatedInPlace = false;
        $result = DB::transaction(function () use ($product, $requested, $stock, &$updatedInPlace): CartQuantityResult {
            // Позицию ищем в базе под блокировкой: снимок корзины в памяти мог устареть
            $offer = app(\App\Services\Pricing\PrivateOfferAccess::class)->grants()->get($product->id);
            $sameSku = Cart::model()?->items()->byProduct($product)->lockForUpdate()->get() ?? collect();
            $item = $sameSku->first(fn ($row) => (($row->configuration ?? [])['private_offer_hash'] ?? null) === $offer?->token_hash);
            $previous = $item ? (int) $item->quantity : 0;
            $otherQuantity = (int) $sameSku->sum('quantity') - $previous;

            $limit = $this->policy->limit($product, $requested, $previous + $otherQuantity, $stock);
            if ($limit->quantity <= 0) {
                throw new CartProductUnavailableException($stock);
            }

            if ($item) {
                $item->update(['quantity' => $previous + $limit->quantity, 'price' => $product->getPrice()]);
                $updatedInPlace = true;
            } else {
                $item = Cart::addItem($product, $limit->quantity, ['attributes' => ['configuration' => ['private_offer_hash' => $offer?->token_hash]]], true);
            }

            app(\App\Services\Inventory\CartStockReservations::class)->reserve($product, $otherQuantity + $previous + $limit->quantity);
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
