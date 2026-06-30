<?php

namespace App\Services\Payment\Contracts;

use App\Models\Payment\PaymentMethod;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Support\Collection;

/**
 * Интерфейс для проверки доступности методов оплаты
 */
interface PaymentMethodAvailabilityInterface
{
    /**
     * Получить доступные методы оплаты для локации
     *
     * @param ShippingLocation|null $location Локация доставки (null = все методы)
     * @return Collection Коллекция методов оплаты
     */
    public function getAvailablePaymentMethods(?ShippingLocation $location): Collection;

    /**
     * Проверить, доступен ли метод оплаты для локации
     *
     * @param PaymentMethod $paymentMethod Метод оплаты
     * @param ShippingLocation $location Локация доставки
     * @return bool
     */
    public function isPaymentMethodAvailable(PaymentMethod $paymentMethod, ShippingLocation $location): bool;
}
