<?php

namespace App\Actions\Cart;

use App\Actions\Cart\Data\CartQuantityResult;
use App\Actions\Cart\Exceptions\CartItemNotFoundException;
use App\Actions\Cart\Exceptions\CartProductUnavailableException;
use App\Models\Product\Product;
use App\Services\Inventory\StockAvailabilityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Vanilo\Cart\Events\CartUpdated;
use Vanilo\Cart\Contracts\CartItem;
use Vanilo\Cart\Facades\Cart;

/**
 * Устанавливает конечное количество позиции; со сменой вариации заменяет позицию.
 */
class UpdateCartItemQuantityAction
{
    public function __construct(
        protected CartQuantityPolicy $policy,
        protected StockAvailabilityService $stockAvailability,
    ) {
    }

    /**
     * @throws CartItemNotFoundException
     * @throws CartProductUnavailableException
     */
    public function execute(CartItem&Model $item, Product $product, int $requested, ?int $shippingLocationId): CartQuantityResult
    {
        $stock = $this->stockAvailability->resolveAvailableStock($product, $shippingLocationId);
        $keepsProduct = (int) $item->product_id === (int) $product->getKey();

        $updatedInPlace = false;
        $result = DB::transaction(function () use ($item, $product, $requested, $stock, $keepsProduct, &$updatedInPlace): CartQuantityResult {
            $locked = $item->newQuery()->whereKey($item->getKey())->lockForUpdate()->first();
            if (! $locked) {
                throw new CartItemNotFoundException();
            }

            $previous = (int) $locked->quantity;
            $limit = $this->policy->limit($product, $requested, 0, $stock);
            if ($limit->quantity <= 0) {
                throw new CartProductUnavailableException($stock);
            }

            // Та же позиция сохраняет id: фронт шлёт следующие изменения по нему
            if ($keepsProduct) {
                $locked->update(['quantity' => $limit->quantity, 'price' => $product->getPrice()]);
                $updated = $locked;
                $updatedInPlace = true;
            } else {
                Cart::removeItem($locked);
                $updated = Cart::addItem($product, $limit->quantity);
            }

            return new CartQuantityResult($updated, $requested, $limit->quantity, $previous, $stock, $limit->reason);
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
