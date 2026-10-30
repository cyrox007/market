<?php

namespace App\Http\Resources;

use App\Models\Shipping\ShippingLocation;
use App\Services\Inventory\WarehouseStockResolver;
use App\Services\Product\ProductRegionRuleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->product ?? $this->buyable;

        // ВАЖНО: Если продукт не найден, возвращаем базовые данные
        if (!$product) {
            return [
                'id' => $this->id ?? $this->getKey(),
                'product_id' => null,
                'name' => 'Товар не найден',
                'slug' => null,
                'price' => (float) ($this->price ?? 0),
                'quantity' => (int) ($this->quantity ?? 1),
                'total' => (float) ($this->price ?? 0) * (int) ($this->quantity ?? 1),
                'old_price' => null,
                'old_total' => null,
                'discount_amount' => 0.0,
                'discount_percent' => null,
                'image' => null,
                'sku' => null,
                'is_variant' => false,
                'variation_attributes' => [],
                'delivery_days' => null,
            ];
        }

        // Если это вариация, получаем родительский товар для slug
        $productForSlug = $product;
        if ($product && method_exists($product, 'isVariant') && $product->isVariant()) {
            // Загружаем родительский товар, если он еще не загружен
            if (!$product->relationLoaded('parentProduct') && $product->parent_product_id) {
                $product->load('parentProduct');
            }
            $productForSlug = $product->parentProduct ?? $product;
        }

        // Вариация: собственные атрибуты из общего хранилища характеристик
        $variationAttributes = [];
        if ($product && method_exists($product, 'isVariant') && $product->isVariant() && method_exists($product, 'getVariationAttributesForApi')) {
            $variationAttributes = $product->getVariationAttributesForApi();
        }

        // Применяем региональные правила для цены
        $basePrice = (float) ($this->price ?? $product->price ?? 0);
        $regionalPrice = $basePrice;
        $deliveryDays = null;

        $regionId = $request->get('_region_id');
        $region = $request->get('_region');
        $warehouseStockResolver = app(WarehouseStockResolver::class);
        if (!$region && $regionId) {
            $region = ShippingLocation::find($regionId);
        }
        if ($product) {
            $regionRuleService = app(ProductRegionRuleService::class);
            $variant = $product->isVariant() ? $product : null;

            // ВАЖНО: Определяем родительский товар для применения правил корзины
            // Если это вариация, загружаем родительский товар, если он еще не загружен
            $parentProduct = $product;
            if ($product->isVariant()) {
                // Загружаем родительский товар, если он еще не загружен
                if (!$product->relationLoaded('parentProduct') && $product->parent_product_id) {
                    $product->load('parentProduct');
                }
                $parentProduct = $product->parentProduct ?? $product;
            }

            // ВАЖНО: Проверяем, что parentProduct не null перед вызовом getPriceForRegion
            if ($parentProduct) {
                $calculatedPrice = app(\App\Services\Pricing\PrivateOfferAccess::class)->withItem($this->resource,
                    fn () => $regionRuleService->getPriceForRegion($parentProduct, $region, $variant));
                if ($calculatedPrice !== null) {
                    $regionalPrice = $calculatedPrice;
                }

                $deliveryDays = $regionRuleService->getDeliveryDaysForRegion($parentProduct, $region, $variant);
            }
        }

        $bundleQuote = null;
        $isBundle = $product && ($product->is_sellable_bundle || $product->parentProduct?->is_sellable_bundle);
        if ($isBundle) {
            $root = $product->isVariant() ? $product->parentProduct : $product;
            try {
                $bundleQuote = app(\App\Services\Product\SellableBundle::class)->quote($root->id, $product->isVariant() ? $product->id : null, 1, $region);
                $regionalPrice = $bundleQuote['subtotal'];
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Keep a stale cart line visible so the buyer can remove it.
            }
        }
        $resolvedStock = $bundleQuote !== null ? app(\App\Services\Product\SellableBundle::class)->availableSets($bundleQuote['items'])
            : ($product ? $warehouseStockResolver->resolveForProduct($product, $region) : 0);

        $quantity = (int) ($this->quantity ?? 1);
        $total = round($regionalPrice * $quantity, 2);
        // Same comparison rule as catalogue: no invented strike-through
        // for fixed-price bundles or private price links.
        $oldPrice = $isBundle ? null : app(\App\Services\Pricing\PricingEngine::class)
            ->oldPrice($product, $region, (float) $regionalPrice);
        $oldPrice = $oldPrice !== null && $oldPrice > $regionalPrice ? round((float) $oldPrice, 2) : null;
        $oldTotal = $oldPrice === null ? null : round($oldPrice * $quantity, 2);
        $discountAmount = $oldTotal === null ? 0.0 : max(0.0, round($oldTotal - $total, 2));

        return [
            'id' => $this->id ?? $this->getKey(),
            'product_id' => $product->id ?? null,
            'name' => $product->name ?? null,
            'slug' => $productForSlug->slug ?? null, // Slug для ссылки на товар (родительский, если это вариация)
            'price' => $regionalPrice,
            'old_price' => $oldPrice,
            'old_total' => $oldTotal,
            'discount_amount' => $discountAmount,
            'discount_percent' => $oldPrice === null ? null : (int) round((1 - $regionalPrice / $oldPrice) * 100),
            'is_sellable_bundle' => (bool) $isBundle,
            'is_bundle_configured' => ! $isBundle || $bundleQuote !== null,
            'available_to_order' => $isBundle ? ($bundleQuote !== null && app(\App\Services\Product\SellableBundle::class)->canOrder($bundleQuote['items']))
                : ($product ? $warehouseStockResolver->availableToOrder($product, $region) : false),
            'bundle_components' => $bundleQuote['priced_components'] ?? [],
            'quantity' => $quantity,
            'price_source' => filled(($this->configuration ?? [])['private_offer_hash'] ?? null) ? 'private_link' : 'catalog',
            'reservation' => $product ? app(\App\Services\Inventory\CartStockReservations::class)->forProduct($product->id) : null,
            'total' => $total,
            // В корзине используем миниатюру, чтобы не тянуть большие изображения
            'image' => $product->thumbnail_url ?? $product->main_image_url ?? null,
            'sku' => $product->sku ?? null,
            'is_variant' => $product ? $product->isVariant() : false,
            'variation_attributes' => $variationAttributes,
            'delivery_days' => $deliveryDays,
            'stock' => $resolvedStock,
        ];
    }
}
