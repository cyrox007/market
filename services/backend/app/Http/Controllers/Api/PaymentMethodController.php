<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment\PaymentMethod;
use App\Models\Shipping\ShippingLocation;
use App\Services\Payment\Contracts\PaymentMethodAvailabilityInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(
        private PaymentMethodAvailabilityInterface $paymentMethodAvailability
    ) {
    }

    /**
     * Получить список активных методов оплаты
     */
    public function index(Request $request): JsonResponse
    {
        // Получаем локацию доставки из запроса (если передан)
        // Может быть любого типа: федеральный округ, регион, город
        $location = null;
        $locationId = $request->get('location_id') ?? $request->get('region_id') ?? $request->get('shipping_location_id');

        if ($locationId) {
            $location = ShippingLocation::where('id', $locationId)
                ->where('is_active', true)
                ->first();
        }

        // Получаем методы оплаты через интерфейс
        $methods = $this->paymentMethodAvailability->getAvailablePaymentMethods($location);
        $methods = $methods->filter(fn (PaymentMethod $method): bool => $method->isGloballyActive())
            ->merge(PaymentMethod::where('is_coming_soon', true)->ordered()->get())
            ->unique('id')->values();

        return response()->json([
            'data' => $methods->map(function ($method) {
                return [
                    'id' => $method->id,
                    'code' => $method->code,
                    'name' => $method->name,
                    'description' => $method->description,
                    'icon' => $method->icon,
                    'is_active' => $method->is_active,
                    'is_coming_soon' => (bool) $method->is_coming_soon,
                    'selectable' => $method->isGloballyActive(),
                    'availability' => $method->is_coming_soon ? 'coming_soon' : 'available',
                    'availability_label' => $method->is_coming_soon ? 'Скоро' : null,
                    'sort_order' => $method->sort_order,
                ];
            }),
        ]);
    }
}
