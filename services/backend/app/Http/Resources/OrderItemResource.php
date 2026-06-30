<?php

namespace App\Http\Resources;

use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use App\Services\Product\ProductRegionRuleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->whenLoaded('product');
        $deliveryDays = null;

        // Получаем region_id из request (передается через OrderResource)
        // Если не передан, пытаемся получить из загруженной связи order
        $regionId = $request->get('_region_id');
        if (!$regionId) {
            $order = $this->whenLoaded('order');
            if ($order && isset($order->region_id) && !($order instanceof \Illuminate\Http\Resources\MissingValue)) {
                $regionId = $order->region_id;
            } elseif ($this->order_id) {
                // Если связь не загружена, но есть order_id, получаем region_id напрямую из заказа
                $order = \App\Models\Order\Order::find($this->order_id);
                if ($order && $order->region_id) {
                    $regionId = $order->region_id;
                }
            }
        }

        // Применяем региональные правила для delivery_days
        // Проверяем, что product загружен и не является MissingValue
        if ($regionId && $this->relationLoaded('product')) {
            $loadedProduct = $this->product;
            if ($loadedProduct) {
                $region = ShippingLocation::find($regionId);
                if ($region) {
                    $regionRuleService = app(ProductRegionRuleService::class);
                    $variant = ($loadedProduct instanceof Product && $loadedProduct->isVariant()) ? $loadedProduct : null;
                    $parentProduct = ($loadedProduct instanceof Product && $loadedProduct->isVariant())
                        ? $loadedProduct->parentProduct
                        : $loadedProduct;

                    if ($parentProduct instanceof Product) {
                        $deliveryDays = $regionRuleService->getDeliveryDaysForRegion($parentProduct, $region, $variant);
                    }
                }
            }
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => new ProductResource($product),
            'quantity' => (int) $this->quantity,
            'price' => (float) $this->price, // Цена уже сохранена с учетом правил при создании заказа
            'total' => (float) $this->total,
            'delivery_days' => $deliveryDays,
        ];
    }
}
