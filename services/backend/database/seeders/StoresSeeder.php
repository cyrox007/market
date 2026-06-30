<?php

namespace Database\Seeders;

use App\Models\Page\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StoresSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stores = [
            [
                'name' => 'ТЦ "Мега"',
                'address' => 'ул. Ленина, 123',
                'city' => 'Москва',
                'phone' => '+7 (495) 123-45-67',
                'hours' => 'Пн-Вс: 10:00 - 22:00',
                'coordinates' => '55.751244, 37.618423',
                'latitude' => 55.751244,
                'longitude' => 37.618423,
                'description' => 'Крупный торговый центр с широким ассортиментом мебели. Удобное расположение в центре города.',
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'ТЦ "Европейский"',
                'address' => 'пр. Мира, 45',
                'city' => 'Москва',
                'phone' => '+7 (495) 234-56-78',
                'hours' => 'Пн-Вс: 10:00 - 22:00',
                'coordinates' => '55.751244, 37.618423',
                'latitude' => 55.751244,
                'longitude' => 37.618423,
                'description' => 'Современный торговый центр с европейским ассортиментом мебели премиум класса.',
                'priority' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'ТРК "Галерея"',
                'address' => 'ул. Невский проспект, 78',
                'city' => 'Санкт-Петербург',
                'phone' => '+7 (812) 345-67-89',
                'hours' => 'Пн-Вс: 10:00 - 22:00',
                'coordinates' => '59.934280, 30.335099',
                'latitude' => 59.934280,
                'longitude' => 30.335099,
                'description' => 'Торгово-развлекательный комплекс на главной улице Санкт-Петербурга. Большой выбор мебели и аксессуаров.',
                'priority' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'ТЦ "Аврора"',
                'address' => 'ул. Московская, 56',
                'city' => 'Санкт-Петербург',
                'phone' => '+7 (812) 456-78-90',
                'hours' => 'Пн-Вс: 10:00 - 22:00',
                'coordinates' => '59.934280, 30.335099',
                'latitude' => 59.934280,
                'longitude' => 30.335099,
                'description' => 'Стильный торговый центр с современной мебелью и дизайнерскими решениями для дома.',
                'priority' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'ТРЦ "Парк Хаус"',
                'address' => 'ул. Красная, 34',
                'city' => 'Казань',
                'phone' => '+7 (843) 567-89-01',
                'hours' => 'Пн-Вс: 10:00 - 22:00',
                'coordinates' => '55.796127, 49.106414',
                'latitude' => 55.796127,
                'longitude' => 49.106414,
                'description' => 'Крупнейший торгово-развлекательный центр в Казани. Широкий выбор мебели для дома и офиса.',
                'priority' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'ТЦ "Сити Молл"',
                'address' => 'пр. Ленина, 89',
                'city' => 'Екатеринбург',
                'phone' => '+7 (343) 678-90-12',
                'hours' => 'Пн-Вс: 10:00 - 22:00',
                'coordinates' => '56.838011, 60.597474',
                'latitude' => 56.838011,
                'longitude' => 60.597474,
                'description' => 'Современный торговый центр в центре Екатеринбурга. Мебель от ведущих производителей.',
                'priority' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($stores as $storeData) {
            $slug = Str::slug($storeData['name']);

            Store::updateOrCreate(
                ['slug' => $slug],
                array_merge($storeData, [
                    'slug' => $slug,
                ])
            );
        }

        $this->command->info('Создано ' . count($stores) . ' магазинов.');
    }
}
