<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Models\Address\Address;
use App\Services\Address\DeliveryAddressResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AddressController extends Controller
{
    public function __construct(private readonly DeliveryAddressResolver $resolver) {}

    private function addressInput(Request $request, ?Address $address = null): array
    {
        $validated = $request->validate([
            'directory_external_id' => [$address ? 'sometimes' : 'required', 'string', 'max:128'],
            'title' => 'nullable|string|max:255',
            'apartment' => 'nullable|string|max:64',
            'entrance' => 'nullable|string|max:64',
            'room' => 'nullable|string|max:64',
            'floor' => 'nullable|string|max:64',
            'intercom' => 'nullable|string|max:64',
            'comment' => 'nullable|string|max:2000',
            'city' => 'prohibited', 'street' => 'prohibited', 'house' => 'prohibited',
            'shipping_location_id' => 'prohibited',
        ]);
        $id = $validated['directory_external_id'] ?? null;
        // An unchanged saved destination remains editable even during directory outages.
        if ($id && $id !== $address?->directory_external_id) {
            try {
                $validated = array_merge($validated, $this->resolver->resolve($id));
            } catch (ValidationException $error) {
                throw $error;
            } catch (Throwable $error) {
                report($error);
                abort(503, 'Адресный справочник временно недоступен. Адрес не сохранён.');
            }
        }

        return $validated;
    }

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user) {
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
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $this->addressInput($request);
        $address = $user->addresses()->create(array_merge($validated, [
            'title' => $validated['title'] ?? 'Адрес доставки', 'is_default' => false,
        ]));

        $address->load('shippingLocation');

        return response()->json([
            'address' => new AddressResource($address),
        ], 201);
    }

    public function show(Request $request, Address $address): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user) {
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
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Проверяем, что адрес принадлежит пользователю
        if ($address->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $address->fill($this->addressInput($request, $address));
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

        if (! $user) {
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

        if (! $user) {
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
