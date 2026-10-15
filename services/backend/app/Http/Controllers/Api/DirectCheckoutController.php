<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipping\ShippingLocation;
use App\Services\Order\DirectCheckout;
use App\Services\Pricing\PricingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectCheckoutController extends Controller
{
    public function __invoke(Request $request, DirectCheckout $checkout, PricingEngine $pricing): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer', 'variant_id' => 'nullable|integer',
            'quantity' => 'required|integer|min:1|max:1000',
            'region_id' => 'nullable|integer|exists:shipping_locations,id',
        ]);
        $item = $checkout->items([$data])->first();
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
