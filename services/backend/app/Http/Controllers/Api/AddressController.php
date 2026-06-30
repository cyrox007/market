<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Models\Address\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $addresses = $user->addresses()
            ->with('shippingLocation')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return AddressResource::collection($addresses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'street' => 'required|string|max:255',
            'house' => 'required|string|max:255',
            'apartment' => 'nullable|string|max:255',
            'entrance' => 'nullable|string|max:255',
            'shipping_location_id' => 'nullable|exists:shipping_locations,id',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $address = $user->addresses()->create([
            'title' => $validated['title'] ?? 'Адрес доставки',
            'city' => $validated['city'],
            'street' => $validated['street'],
            'house' => $validated['house'],
            'apartment' => $validated['apartment'] ?? null,
            'entrance' => $validated['entrance'] ?? null,
            'is_default' => false,
            'shipping_location_id' => $validated['shipping_location_id'] ?? null,
        ]);

        $address->load('shippingLocation');
        
        return response()->json([
            'address' => new AddressResource($address),
        ], 201);
    }

    public function show(Request $request, Address $address): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Проверяем, что адрес принадлежит пользователю
        if ($address->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $address->load('shippingLocation');

        return response()->json([
            'address' => new AddressResource($address),
        ]);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'street' => 'sometimes|required|string|max:255',
            'house' => 'sometimes|required|string|max:255',
            'apartment' => 'nullable|string|max:255',
            'entrance' => 'nullable|string|max:255',
            'shipping_location_id' => 'nullable|exists:shipping_locations,id',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Проверяем, что адрес принадлежит пользователю
        if ($address->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $address->fill($validated);
        $address->save();
        $address->load('shippingLocation');

        return response()->json([
            'address' => new AddressResource($address),
        ]);
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Проверяем, что адрес принадлежит пользователю
        if ($address->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        DB::transaction(function () use ($user, $address): void {
            // Detach historical orders from this address to avoid FK issues
            // on environments where old schema may still use RESTRICT.
            DB::table('orders')
                ->where('user_id', $user->id)
                ->where('address_id', $address->id)
                ->update(['address_id' => null]);

            $address->delete();
        });

        return response()->json([
            'message' => 'Адрес успешно удален',
        ]);
    }

    public function setDefault(Request $request, Address $address): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Проверяем, что адрес принадлежит пользователю
        if ($address->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Снимаем флаг is_default со всех адресов пользователя
        $user->addresses()->update(['is_default' => false]);

        // Устанавливаем is_default для выбранного адреса
        $address->is_default = true;
        $address->save();

        return response()->json([
            'message' => 'Адрес установлен как основной',
        ]);
    }
}
