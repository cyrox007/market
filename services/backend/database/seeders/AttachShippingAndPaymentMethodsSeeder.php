<?php

namespace Database\Seeders;

use App\Models\Payment\PaymentMethod;
use App\Models\Shipping\Carrier;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Database\Seeder;

class AttachShippingAndPaymentMethodsSeeder extends Seeder
{
    /**
     * Привязывает методы доставки и оплаты к локациям
     * 
     * Стратегия:
     * - Методы оплаты привязываются к регионам (type = 'region'), чтобы наследоваться дочерними локациями
     * - Методы доставки (carriers) привязываются к регионам
     * - Можно настроить привязку ко всем локациям или только к определенным уровням
     */
    public function run(): void
    {
        $this->command->info('Начало привязки методов доставки и оплаты к локациям...');

        // Получаем все методы оплаты
        // Используем is_enabled вместо active(), так как is_active может отсутствовать до миграции
        $paymentMethods = PaymentMethod::where('is_enabled', true)->get();
        if ($paymentMethods->isEmpty()) {
            $this->command->warn('Методы оплаты не найдены. Запустите: php artisan db:seed --class=PaymentMethodSeeder');
            return;
        }
        $this->command->info('Найдено методов оплаты: ' . $paymentMethods->count());

        // Получаем все carriers (методы доставки)
        $carriers = Carrier::where('is_active', true)->get();
        if ($carriers->isEmpty()) {
            $this->command->warn('Методы доставки не найдены. Запустите: php artisan db:seed --class=CarriersSeeder');
            return;
        }
        $this->command->info('Найдено методов доставки: ' . $carriers->count());

        // Стратегия 1: Привязать ко всем регионам (type = 'region')
        $regions = ShippingLocation::where('type', 'region')
            ->where('is_active', true)
            ->get();

        $this->command->info('Найдено регионов: ' . $regions->count());

        $paymentAttached = 0;
        $carrierAttached = 0;

        foreach ($regions as $region) {
            // Привязываем методы оплаты
            foreach ($paymentMethods as $index => $paymentMethod) {
                // Проверяем, не привязан ли уже
                if (!$region->paymentMethods()->where('payment_methods.id', $paymentMethod->id)->exists()) {
                    $region->paymentMethods()->attach($paymentMethod->id, [
                        'is_active' => true,
                        'sort_order' => $index + 1,
                    ]);
                    $paymentAttached++;
                }
            }

            // Привязываем методы доставки
            foreach ($carriers as $index => $carrier) {
                // Проверяем, не привязан ли уже
                if (!$region->carriers()->where('carriers.id', $carrier->id)->exists()) {
                    $region->carriers()->attach($carrier->id, [
                        'is_active' => true,
                        'sort_order' => $index + 1,
                        'base_price' => null, // Можно настроить индивидуально
                        'free_delivery_threshold' => null,
                        'delivery_days_min' => null,
                        'delivery_days_max' => null,
                    ]);
                    $carrierAttached++;
                }
            }
        }

        $this->command->info("✅ Привязка завершена!");
        $this->command->info("   - Привязано методов оплаты: {$paymentAttached}");
        $this->command->info("   - Привязано методов доставки: {$carrierAttached}");

        // Опционально: привязать к федеральным округам (если нужно)
        $federalDistricts = ShippingLocation::where('type', 'federal_district')
            ->where('is_active', true)
            ->get();

        if ($federalDistricts->isNotEmpty()) {
            $this->command->info("\nПривязка к федеральным округам (опционально)...");
            
            foreach ($federalDistricts as $district) {
                foreach ($paymentMethods as $index => $paymentMethod) {
                    if (!$district->paymentMethods()->where('payment_methods.id', $paymentMethod->id)->exists()) {
                        $district->paymentMethods()->attach($paymentMethod->id, [
                            'is_active' => true,
                            'sort_order' => $index + 1,
                        ]);
                    }
                }

                foreach ($carriers as $index => $carrier) {
                    if (!$district->carriers()->where('carriers.id', $carrier->id)->exists()) {
                        $district->carriers()->attach($carrier->id, [
                            'is_active' => true,
                            'sort_order' => $index + 1,
                        ]);
                    }
                }
            }
            
            $this->command->info("   - Привязано к федеральным округам");
        }
    }
}
