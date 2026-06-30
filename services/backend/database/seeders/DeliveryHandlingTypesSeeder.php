<?php

namespace Database\Seeders;

use App\Models\Shipping\DeliveryHandlingType;
use Illuminate\Database\Seeder;

class DeliveryHandlingTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Лифт',
                'slug' => 'elevator',
                'code' => 'elevator',
                'description' => 'Разгрузка с использованием лифта (любой этаж)',
                'requires_floor' => false,
                'max_floor' => null,
                'requires_elevator' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Ручной подъем',
                'slug' => 'manual_lift',
                'code' => 'manual_lift',
                'description' => 'Ручная разгрузка без лифта (требуется указание этажа, цена зависит от этажа)',
                'requires_floor' => true,
                'max_floor' => 20,
                'requires_elevator' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($types as $type) {
            DeliveryHandlingType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
