<?php

namespace Database\Seeders;

use App\Models\Shipping\ShippingLocation;
use App\Services\Shipping\CarrierService;
use Illuminate\Database\Seeder;

/**
 * Создаёт методы доставки (Vanilo ShippingMethod) для всех активных локаций.
 * Нужен для заполнения выпадающего списка в админке «Методы доставки по регионам».
 */
class ShippingMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $carrierService = app(CarrierService::class);
        $locations = ShippingLocation::where('is_active', true)->get();

        $created = 0;
        foreach ($locations as $location) {
            $methods = $carrierService->createShippingMethodsForLocation($location);
            $created += count($methods);
        }

        $this->command->info("Создано/обновлено методов доставки: {$created} для {$locations->count()} локаций.");
    }
}
