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

        // Вариация: атрибуты из product_variant_attributes
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
        if ($region && $product) {
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
                $calculatedPrice = $regionRuleService->getPriceForRegion($parentProduct, $region, $variant);
                if ($calculatedPrice !== null) {
                    $regionalPrice = $calculatedPrice;
                }

                $deliveryDays = $regionRuleService->getDeliveryDaysForRegion($parentProduct, $region, $variant);
            }
        }

        $resolvedStock = $product ? $warehouseStockResolver->resolveForProduct($product, $region) : 0;

        $quantity = (int) ($this->quantity ?? 1);
        $total = $regionalPrice * $quantity;

        return [
            'id' => $this->id ?? $this->getKey(),
            'product_id' => $product->id ?? null,
            'name' => $product->name ?? null,
            'slug' => $productForSlug->slug ?? null, // Slug для ссылки на товар (родительский, если это вариация)
            'price' => $regionalPrice,
            'quantity' => $quantity,
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
