<?php

namespace Database\Seeders;

use App\Models\Shipping\AdditionalService;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Database\Seeder;

class AdditionalServicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'Сборка мебели',
                'slug' => 'assembly',
                'code' => 'assembly',
                'description' => 'Профессиональная сборка мебели в день доставки. Гарантия 12 месяцев',
                'icon' => 'ri-tools-line',
                'price_type' => 'fixed',
                'base_price' => 1000.00,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Установка техники',
                'slug' => 'appliance-installation',
                'code' => 'appliance_installation',
                'description' => 'Установка и подключение бытовой техники. Включает проверку работоспособности',
                'icon' => 'ri-install-line',
                'price_type' => 'from',
                'base_price' => 1500.00,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Упаковка в коробки',
                'slug' => 'packaging',
                'code' => 'packaging',
                'description' => 'Дополнительная упаковка товаров в коробки для безопасной транспортировки',
                'icon' => 'ri-box-3-line',
                'price_type' => 'fixed',
                'base_price' => 300.00,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Вынос старой мебели',
                'slug' => 'old-furniture-removal',
                'code' => 'old_furniture_removal',
                'description' => 'Вынос и утилизация старой мебели. Цена зависит от объема и сложности',
                'icon' => 'ri-delete-bin-line',
                'price_type' => 'custom',
                'base_price' => null,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Подъем на этаж',
                'slug' => 'floor-lift',
                'code' => 'floor_lift',
                'description' => 'Ручной подъем мебели на этаж без лифта',
                'icon' => 'ri-stairs-line',
                'price_type' => 'from',
                'base_price' => 500.00,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Демонтаж старой мебели',
                'slug' => 'dismantling',
                'code' => 'dismantling',
                'description' => 'Демонтаж старой мебели перед установкой новой. Цена рассчитывается индивидуально',
                'icon' => 'ri-hammer-line',
                'price_type' => 'custom',
                'base_price' => null,
                'is_active' => true,
                'sort_order' => 6,
            ],
        ];

        foreach ($services as $serviceData) {
            $service = AdditionalService::firstOrCreate(
                ['code' => $serviceData['code']],
                $serviceData
            );

            // Привязываем услугу ко всем активным локациям с базовой ценой
            $locations = ShippingLocation::where('is_active', true)->get();
            
            foreach ($locations as $location) {
                // Проверяем, не привязана ли уже услуга к этой локации
                $exists = $location->additionalServices()
                    ->where('additional_services.id', $service->id)
                    ->exists();

                if (!$exists) {
                    // Привязываем с базовой ценой из услуги (если есть)
                    $pivotData = [
                        'price' => $service->base_price,
                        'is_active' => true,
                        'sort_order' => $service->sort_order,
                    ];

                    $location->additionalServices()->attach($service->id, $pivotData);
                }
            }
        }

        $this->command->info('Дополнительные услуги успешно созданы и привязаны ко всем регионам!');
    }
}
