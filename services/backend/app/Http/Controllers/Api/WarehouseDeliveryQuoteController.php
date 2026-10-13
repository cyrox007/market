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
            'region_id' => 'nullable|exists:shipping_locations,id',
        ]);
        $id = $data['directory_external_id'] ?? null;
        if (! empty($data['address_id'])) {
            abort_unless($request->user(), 401);
            $address = Address::where('user_id', $request->user()->id)->findOrFail($data['address_id']);
            $id = $address->directory_external_id;
        }
        if (! $id) {
            return response()->json(['message' => 'Выберите здание из справочника.'], 422);
        }
        try {
            $result = $quotes->calculate($id, $quotes->cartLines(Cart::getItems()), isset($data['region_id']) ? ShippingLocation::find($data['region_id']) : null);
        } catch (\Illuminate\Validation\ValidationException $error) {
            throw $error;
        } catch (\RuntimeException $error) {
            report($error);

            return response()->json(['message' => 'Адресный справочник временно недоступен.'], 503);
        }

        return response()->json((new \App\Http\Resources\WarehouseDeliveryQuoteResource($result))->resolve($request), $result['available'] ? 200 : 422);
    }
}
