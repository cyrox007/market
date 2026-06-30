<?php

namespace Database\Seeders;

use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ShippingLocationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Сначала создаем федеральные округа
        $centralDistrict = ShippingLocation::updateOrCreate(
            ['slug' => 'central', 'type' => 'federal_district'],
            [
                'name' => 'Центральный федеральный округ',
                'code' => 'CFD',
                'type' => 'federal_district',
                'location_type' => 'federal_district',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $southernDistrict = ShippingLocation::updateOrCreate(
            ['slug' => 'southern', 'type' => 'federal_district'],
            [
                'name' => 'Южный федеральный округ',
                'code' => 'SFD',
                'type' => 'federal_district',
                'location_type' => 'federal_district',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // Создаем регионы для Центрального округа
        $belgorodRegion = ShippingLocation::updateOrCreate(
            ['slug' => 'belgorodskaya-oblast', 'parent_id' => $centralDistrict->id],
            [
                'name' => 'Белгородская область',
                'code' => '31',
                'type' => 'region',
                'location_type' => 'oblast',
                'parent_id' => $centralDistrict->id,
                'delivery_price' => 1500,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $voronezhRegion = ShippingLocation::updateOrCreate(
            ['slug' => 'voronezhskaya-oblast', 'parent_id' => $centralDistrict->id],
            [
                'name' => 'Воронежская область',
                'code' => '36',
                'type' => 'region',
                'location_type' => 'oblast',
                'parent_id' => $centralDistrict->id,
                'delivery_price' => 2100,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // Создаем города для Белгородской области
        ShippingLocation::updateOrCreate(
            ['slug' => 'belgorod', 'parent_id' => $belgorodRegion->id],
            [
                'name' => 'г. Белгород',
                'type' => 'locality',
                'location_type' => 'city',
                'parent_id' => $belgorodRegion->id,
                'delivery_price' => 1500,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        ShippingLocation::updateOrCreate(
            ['slug' => 'alekseevskiy-rayon', 'parent_id' => $belgorodRegion->id],
            [
                'name' => 'Алексеевский район',
                'type' => 'locality',
                'location_type' => 'district',
                'parent_id' => $belgorodRegion->id,
                'delivery_price' => 1500,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        ShippingLocation::updateOrCreate(
            ['slug' => 'gubkinskiy-rayon', 'parent_id' => $belgorodRegion->id],
            [
                'name' => 'Губкинский район',
                'type' => 'locality',
                'location_type' => 'district',
                'parent_id' => $belgorodRegion->id,
                'delivery_price' => 1500,
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        ShippingLocation::updateOrCreate(
            ['slug' => 'starooskolskiy-gorodskoy-okrug', 'parent_id' => $belgorodRegion->id],
            [
                'name' => 'Старооскольский городской округ',
                'type' => 'locality',
                'location_type' => 'urban_settlement',
                'parent_id' => $belgorodRegion->id,
                'delivery_price' => 1500,
                'is_active' => true,
                'sort_order' => 4,
            ]
        );

        // Создаем города для Воронежской области
        ShippingLocation::updateOrCreate(
            ['slug' => 'voronezh', 'parent_id' => $voronezhRegion->id],
            [
                'name' => 'Воронеж',
                'type' => 'locality',
                'location_type' => 'city',
                'parent_id' => $voronezhRegion->id,
                'delivery_price' => 2100,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        ShippingLocation::updateOrCreate(
            ['slug' => 'gorodskoy-okrug-novoronezh', 'parent_id' => $voronezhRegion->id],
            [
                'name' => 'городской округ Нововоронеж',
                'type' => 'locality',
                'location_type' => 'urban_settlement',
                'parent_id' => $voronezhRegion->id,
                'delivery_price' => 2100,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // Создаем регионы для Южного округа
        $adygeaRegion = ShippingLocation::updateOrCreate(
            ['slug' => 'adygeya-respublika', 'parent_id' => $southernDistrict->id],
            [
                'name' => 'Адыгея Республика',
                'code' => '01',
                'type' => 'region',
                'location_type' => 'republic',
                'parent_id' => $southernDistrict->id,
                'delivery_price' => 2300,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $volgogradRegion = ShippingLocation::updateOrCreate(
            ['slug' => 'volgogradskaya-oblast', 'parent_id' => $southernDistrict->id],
            [
                'name' => 'Волгоградская область',
                'code' => '34',
                'type' => 'region',
                'location_type' => 'oblast',
                'parent_id' => $southernDistrict->id,
                'delivery_price' => 2600,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // Создаем города для Адыгеи
        ShippingLocation::updateOrCreate(
            ['slug' => 'adygeysk', 'parent_id' => $adygeaRegion->id],
            [
                'name' => 'г. Адыгейск',
                'type' => 'locality',
                'location_type' => 'city',
                'parent_id' => $adygeaRegion->id,
                'delivery_price' => 2300,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        ShippingLocation::updateOrCreate(
            ['slug' => 'maykop', 'parent_id' => $adygeaRegion->id],
            [
                'name' => 'г. Майкоп',
                'type' => 'locality',
                'location_type' => 'city',
                'parent_id' => $adygeaRegion->id,
                'delivery_price' => 2300,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // Создаем города для Волгоградской области
        ShippingLocation::updateOrCreate(
            ['slug' => 'volgograd', 'parent_id' => $volgogradRegion->id],
            [
                'name' => 'г. Волгоград',
                'type' => 'locality',
                'location_type' => 'city',
                'parent_id' => $volgogradRegion->id,
                'delivery_price' => 2600,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        ShippingLocation::updateOrCreate(
            ['slug' => 'volzhskiy', 'parent_id' => $volgogradRegion->id],
            [
                'name' => 'г. Волжский',
                'type' => 'locality',
                'location_type' => 'city',
                'parent_id' => $volgogradRegion->id,
                'delivery_price' => 2600,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // Настраиваем типы обработки доставки для локаций
        $this->setupDeliveryHandlingForLocations();
    }

    /**
     * Настроить типы обработки доставки для локаций
     */
    private function setupDeliveryHandlingForLocations(): void
    {
        $elevatorType = DeliveryHandlingType::where('code', 'elevator')->first();
        $manualLiftType = DeliveryHandlingType::where('code', 'manual_lift')->first();

        if (!$elevatorType || !$manualLiftType) {
            return;
        }

        // Получаем все города (locality)
        $cities = ShippingLocation::where('type', 'locality')
            ->where('location_type', 'city')
            ->get();

        foreach ($cities as $city) {
            // Добавляем лифт с фиксированной ценой 500
            $city->deliveryHandlingTypes()->syncWithoutDetaching([
                $elevatorType->id => [
                    'elevator_price' => 500,
                    'is_active' => true,
                ],
            ]);

            // Добавляем ручной подъем с ценами по этажам
            $floorPrices = [];
            for ($floor = 1; $floor <= 20; $floor++) {
                $floorPrices[$floor] = 300 + ($floor - 1) * 200; // 300, 500, 700, ..., 4100
            }

            $city->deliveryHandlingTypes()->syncWithoutDetaching([
                $manualLiftType->id => [
                    'floor_prices' => json_encode($floorPrices), // Преобразуем массив в JSON строку
                    'is_active' => true,
                ],
            ]);
        }
    }
}
