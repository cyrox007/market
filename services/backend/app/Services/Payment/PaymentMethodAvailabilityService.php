<?php

namespace App\Services\Payment;

use App\Models\Payment\PaymentMethod;
use App\Models\Shipping\ShippingLocation;
use App\Services\Payment\Contracts\PaymentMethodAvailabilityInterface;
use Illuminate\Support\Collection;

/**
 * Сервис для проверки доступности методов оплаты
 * Реализует PaymentMethodAvailabilityInterface
 */
class PaymentMethodAvailabilityService implements PaymentMethodAvailabilityInterface
{
    /**
     * Получить доступные методы оплаты для локации
     */
    public function getAvailablePaymentMethods(?ShippingLocation $location): Collection
    {
        // Если локация не указана, возвращаем все активные методы
        if (!$location) {
            return PaymentMethod::active()->ordered()->get();
        }

        // Получаем методы оплаты для локации с учетом иерархии
        $paymentMethods = $location->getEffectivePaymentMethods();

        if ($paymentMethods->isEmpty()) {
            // Если методов оплаты нет, возвращаем все активные методы (fallback)
            return PaymentMethod::active()->ordered()->get();
        }

        // Сортируем по sort_order из pivot
        return $paymentMethods->sortBy(function ($method) use ($location) {
            $locationIds = array_reverse($location->getAncestorsIds());
            
            foreach ($locationIds as $locId) {
                $tempLocation = ShippingLocation::find($locId);
                if ($tempLocation) {
                    $pivot = $tempLocation->paymentMethods()
                        ->where('payment_methods.id', $method->id)
                        ->wherePivot('is_active', true)
                        ->first()?->pivot;
                    
                    if ($pivot) {
                        return $pivot->sort_order ?? 999;
                    }
                }
            }
            
            return $method->sort_order ?? 999;
        })->values();
    }

    /**
     * Проверить, доступен ли метод оплаты для локации
     */
    public function isPaymentMethodAvailable(PaymentMethod $paymentMethod, ShippingLocation $location): bool
    {
        if (!$paymentMethod->isGloballyActive()) {
            return false;
        }

        // Проверяем, есть ли связь между методом оплаты и локацией (с учетом иерархии)
        $locationIds = array_reverse($location->getAncestorsIds());
        
        foreach ($locationIds as $locId) {
            $tempLocation = ShippingLocation::find($locId);
            if ($tempLocation) {
                $pivot = $tempLocation->paymentMethods()
                    ->where('payment_methods.id', $paymentMethod->id)
                    ->wherePivot('is_active', true)
                    ->first()?->pivot;
                
                if ($pivot) {
                    return true;
                }
            }
        }

        // Если связи нет, метод доступен по умолчанию (fallback), но только если глобально активен
        return $paymentMethod->isGloballyActive();
    }
}
