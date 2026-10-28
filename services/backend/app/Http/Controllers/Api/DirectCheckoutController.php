<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipping\ShippingLocation;
use App\Services\Order\DirectCheckout;
use App\Services\Pricing\PricingEngine;
use App\Services\Product\SellableBundle;
use App\Models\Product\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectCheckoutController extends Controller
{
    public function __invoke(Request $request, DirectCheckout $checkout, PricingEngine $pricing): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required_without:bundle_id|integer', 'variant_id' => 'nullable|integer',
            'bundle_id' => 'sometimes|integer', 'primary_sku_id' => 'nullable|integer',
            'quantity' => 'required|integer|min:1|max:1000',
            'region_id' => 'nullable|integer|exists:shipping_locations,id',
        ]);
        if (isset($data['bundle_id'])) {
            $region = ShippingLocation::find($data['region_id'] ?? null);
            $quote = app(SellableBundle::class)->quote(
                (int) $data['bundle_id'], isset($data['primary_sku_id']) ? (int) $data['primary_sku_id'] : null,
                (int) $data['quantity'], $region
            );
            return response()->json([
                'mode' => 'direct_bundle_checkout',
                ...$quote,
                'submit_endpoint' => '/api/v1/orders',
                'message' => 'Проверьте склад и оформите весь комплект одной покупкой.',
            ]);
        }
        $item = $checkout->items([$data])->first();
        if ($item->buyable->is_sellable_bundle || $item->buyable->parentProduct?->is_sellable_bundle) {
            throw \Illuminate\Validation\ValidationException::withMessages(['product_id' => 'Этот товар продаётся комплектом. Выберите покупку комплекта.']);
        }
        $price = $pricing->explain($item->buyable, ShippingLocation::find($data['region_id'] ?? null));

        return response()->json([
            'mode' => 'direct_checkout',
            'items' => [['product_id' => $item->product_id, 'quantity' => $item->quantity]],
            'unit_price' => $price['price'], 'subtotal' => round($price['price'] * $item->quantity, 2),
            'submit_endpoint' => '/api/v1/orders',
            'message' => 'Перейдите к оформлению заказа. Корзина не изменена; заказ ещё не создан.',
        ]);
    }
}
