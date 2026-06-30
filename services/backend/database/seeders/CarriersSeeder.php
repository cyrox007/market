<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shipping\Carrier;

class CarriersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Стандартная доставка
        Carrier::firstOrCreate(
            ['name' => 'Стандартная доставка'],
            [
                'name' => 'Стандартная доставка',
                'is_active' => true,
                'configuration' => [
                    'type' => 'standard',
                    'description' => 'Обычная доставка курьером',
                ],
            ]
        );

        // Экспресс-доставка
        Carrier::firstOrCreate(
            ['name' => 'Экспресс-доставка'],
            [
                'name' => 'Экспресс-доставка',
                'is_active' => true,
                'configuration' => [
                    'type' => 'express',
                    'description' => 'Быстрая доставка в день заказа',
                ],
            ]
        );

        // Доставка в регионы
        Carrier::firstOrCreate(
            ['name' => 'Доставка в регионы'],
            [
                'name' => 'Доставка в регионы',
                'is_active' => true,
                'configuration' => [
                    'type' => 'regional',
                    'description' => 'Доставка по всей России через транспортные компании',
                ],
            ]
        );
    }
}
