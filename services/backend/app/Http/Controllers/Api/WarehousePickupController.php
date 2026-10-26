<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shipping\FulfillmentLines;
use App\Services\Shipping\WarehouseDeliveryQuote;
use App\Services\Shipping\WarehousePickup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Vanilo\Cart\Facades\Cart;

class WarehousePickupController extends Controller
{
    public function __invoke(Request $request, WarehousePickup $pickup, WarehouseDeliveryQuote $quotes): JsonResponse
    {
        $data = $request->validate([
            'items' => 'sometimes|array|min:1|max:50',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'nullable|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
        ]);
        $lines = app(FulfillmentLines::class)->resolve($data['items'] ?? $quotes->cartLines(Cart::getItems()));
        if (! $lines) {
            throw ValidationException::withMessages(['cart' => 'Корзина пуста.']);
        }

        return response()->json(['pickup' => array_map(
            fn (array $option): array => \Illuminate\Support\Arr::except($option, ['warehouse_fingerprint', 'store_fingerprint']),
            $pickup->options($lines),
        )]);
    }
}
