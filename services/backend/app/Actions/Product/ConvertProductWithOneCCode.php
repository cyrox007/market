<?php

namespace App\Actions\Product;

use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConvertProductWithOneCCode
{
    public function execute(Product $product, string $expectedCode, ?\Closure $createOffer = null): ?Product
    {
        return DB::transaction(function () use ($product, $expectedCode, $createOffer): ?Product {
            $parent = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            if ($parent->isVariant() || $parent->is_sellable_bundle || blank($parent->external_id)
                || $parent->external_id !== $expectedCode) {
                throw ValidationException::withMessages(['operation' => 'Карточка или код 1С изменились. Обновите страницу.']);
            }

            $stocks = $parent->warehouseStocks()->lockForUpdate()->get();
            $cartReservations = DB::table('cart_stock_reservations')->where('product_id', $parent->id)
                ->where('expires_at', '>', now())->lockForUpdate()->get(['id']);
            if ($stocks->contains(fn ($stock) => (float) $stock->reserved_quantity > 0) || $cartReservations->isNotEmpty()) {
                throw ValidationException::withMessages(['operation' => 'У товара есть действующие резервы. Освободите их перед переносом кода 1С.']);
            }

            $parent->update([
                'external_id' => null,
                'is_variable' => true,
                ...($createOffer ? ['sku' => null, 'stock' => 0, 'backorder' => false] : []),
            ]);

            if ($createOffer === null) {
                Product::flushAllProductCaches();

                return null;
            }

            $variant = $createOffer($parent);
            $parent->warehouseStocks()->delete();
            DB::table('source_product_availabilities')->where('product_id', $parent->id)->update(['product_id' => $variant->id]);

            return $variant;
        });
    }
}
