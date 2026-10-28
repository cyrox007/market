<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address\Address;
use App\Models\Shipping\ShippingLocation;
use App\Services\Shipping\WarehouseDeliveryQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Vanilo\Cart\Facades\Cart;

class WarehouseDeliveryQuoteController extends Controller
{
    public function __invoke(Request $request, WarehouseDeliveryQuote $quotes): JsonResponse
    {
        $data = $request->validate([
            'directory_external_id' => 'required_without:address_id|string|max:128',
            'address_id' => 'required_without:directory_external_id|integer',
            'region_id' => ['nullable', \Illuminate\Validation\Rule::exists('shipping_locations', 'id')->where('is_active', true)],
            'items' => 'sometimes|array|min:1|max:50',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'nullable|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'requires_assembly' => 'sometimes|boolean',
            'delivery_handling_type_id' => 'nullable|integer|exists:delivery_handling_types,id',
            'delivery_floor' => 'nullable|integer|min:1|max:20',
            'additional_services' => 'sometimes|array|max:20',
            'additional_services.*.id' => 'required|integer|distinct|exists:additional_services,id',
            'additional_services.*.price' => 'prohibited',
        ]);
        $id = $data['directory_external_id'] ?? null;
        if (! empty($data['address_id'])) {
            abort_unless($request->user(), 401);
            $address = Address::where('user_id', $request->user()->id)->findOrFail($data['address_id']);
            if ($id && $id !== $address->directory_external_id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['address_id' => 'Сохранённый адрес не совпадает с выбранным.']);
            }
            $id = $address->directory_external_id;
        }
        if (! $id) {
            return response()->json(['message' => 'Выберите здание из справочника.'], 422);
        }
        try {
            $lines = isset($data['items']) ? app(\App\Services\Shipping\FulfillmentLines::class)->resolve($data['items']) : $quotes->cartLines(Cart::getItems());
            // Direct checkout and the cart must use the same PricingEngine as final order creation.
            // Never trust client totals; price the resolved concrete SKU lines on the server.
            $region = isset($data['region_id']) ? ShippingLocation::find($data['region_id']) : null;
            $pricingItems = isset($data['items'])
                ? app(\App\Services\Order\DirectCheckout::class)->items($lines)
                : Cart::getItems();
            $pricedItems = app(\App\Services\Pricing\PrivateOfferAccess::class)->pricedItems($pricingItems, $region);
            $data['_cart_subtotal'] = collect($pricedItems)->sum(fn ($line) => $line['price'] * $line['quantity']);
            $result = $quotes->calculate($id, $lines, isset($data['region_id']) ? ShippingLocation::find($data['region_id']) : null,
                array_merge($data, ['preview' => isset($data['items'])]));
        } catch (\Illuminate\Validation\ValidationException $error) {
            throw $error;
        } catch (\RuntimeException $error) {
            report($error);

            return response()->json(['message' => 'Адресный справочник временно недоступен.'], 503);
        }

        return response()->json((new \App\Http\Resources\WarehouseDeliveryQuoteResource($result))->resolve($request), $result['available'] || isset($data['items']) ? 200 : 422);
    }
}
