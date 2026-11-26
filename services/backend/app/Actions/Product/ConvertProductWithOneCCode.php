<?php

namespace App\Actions\Product;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ConvertProductWithOneCCode
{
    public function execute(Product $product, string $expectedCode, ?string $offerName, ?float $price): ?Product
    {
        return DB::transaction(function () use ($product, $expectedCode, $offerName, $price): ?Product {
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

            $sku = filled($parent->sku) ? $parent->sku : $expectedCode;
            if ($offerName !== null && Product::query()->where('sku', $sku)->whereKeyNot($parent->id)->exists()) {
                throw ValidationException::withMessages(['operation' => 'Артикул уже используется другой карточкой.']);
            }

            $backorder = $parent->backorder;
            $parent->update([
                'external_id' => null,
                'is_variable' => true,
                ...($offerName !== null ? ['sku' => null, 'stock' => 0, 'backorder' => false] : []),
            ]);

            if ($offerName === null) {
                Product::flushAllProductCaches();

                return null;
            }

            $variant = $parent->variants()->create([
                'name' => $offerName,
                'sku' => $sku,
                'slug' => Str::limit(Str::slug($offerName) ?: 'offer', 210, '').'-'.Str::lower(Str::random(12)),
                'external_id' => $expectedCode,
                'price' => $price,
                'original_price' => $parent->original_price,
                'state' => $parent->getRawOriginal('state'),
                'is_variable' => false,
                'stock' => $stocks->sum('quantity'),
                'backorder' => $backorder,
            ]);

            DB::table('product_warehouse_stocks')->whereIn('id', $stocks->modelKeys())->update(['product_id' => $variant->id]);

            DB::table('source_product_availabilities')->where('product_id', $parent->id)->update(['product_id' => $variant->id]);

            $attribute = Attribute::ensureVariantAttribute();
            app(SyncVariantVariationAttributesAction::class)->execute(
                $variant, ['variation_custom_'.$attribute->id => $offerName], $parent, [$attribute->id],
            );
            $parent->flushCache();
            $variant->flushCache();

            return $variant;
        });
    }
}
