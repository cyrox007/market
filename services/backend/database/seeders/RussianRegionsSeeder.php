<?php

namespace Database\Seeders;

use App\Models\Shipping\FederalDistrict;
use App\Models\Shipping\Locality;
use App\Models\Shipping\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RussianRegionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Федеральные округа России
        $federalDistricts = [
            [
                'name' => 'Центральный федеральный округ',
                'code' => 'CFD',
                'slug' => 'central',
            ],
            [
                'name' => 'Северо-Западный федеральный округ',
                'code' => 'NWFD',
                'slug' => 'northwest',
            ],
            [
                'name' => 'Южный федеральный округ',
                'code' => 'SFD',
                'slug' => 'southern',
            ],
            [
                'name' => 'Приволжский федеральный округ',
                'code' => 'VFD',
                'slug' => 'volga',
            ],
            [
                'name' => 'Уральский федеральный округ',
                'code' => 'UFD',
                'slug' => 'ural',
            ],
            [
                'name' => 'Сибирский федеральный округ',
                'code' => 'SIBFD',
                'slug' => 'siberian',
            ],
            [
                'name' => 'Дальневосточный федеральный округ',
                'code' => 'FEFD',
                'slug' => 'far-eastern',
            ],
            [
                'name' => 'Северо-Кавказский федеральный округ',
                'code' => 'NCFD',
                'slug' => 'north-caucasus',
            ],
        ];

        foreach ($federalDistricts as $districtData) {
            $district = FederalDistrict::updateOrCreate(
                ['code' => $districtData['code']],
                [
                    'name' => $districtData['name'],
                    'slug' => $districtData['slug'],
                    'code' => $districtData['code'],
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            );

            // Пример: добавляем несколько основных регионов для каждого округа
            $this->seedRegionsForDistrict($district);
        }
    }

    /**
     * Заполнить регионы для федерального округа
     */
    private function seedRegionsForDistrict(FederalDistrict $district): void
    {
        // Регионы для Центрального федерального округа
        if ($district->code === 'CFD') {
            $regions = [
                ['name' => 'Москва', 'type' => 'federal_city', 'code' => '77'],
                ['name' => 'Московская область', 'type' => 'oblast', 'code' => '50'],
                ['name' => 'Белгородская область', 'type' => 'oblast', 'code' => '31', 'delivery_price' => 1500],
                ['name' => 'Воронежская область', 'type' => 'oblast', 'code' => '36', 'delivery_price' => 2100],
                ['name' => 'Ивановская область', 'type' => 'oblast', 'code' => '37', 'delivery_price' => 1800],
            ];

            foreach ($regions as $regionData) {
                $region = Region::updateOrCreate(
                    [
                        'federal_district_id' => $district->id,
                        'code' => $regionData['code'],
                    ],
                    [
                        'name' => $regionData['name'],
                        'slug' => Str::slug($regionData['name']),
                        'type' => $regionData['type'],
                        'code' => $regionData['code'],
                        'delivery_price' => $regionData['delivery_price'] ?? null,
                        'is_active' => true,
                        'sort_order' => 0,
                    ]
                );

                // Добавляем основные города для региона
                $this->seedLocalitiesForRegion($region);
            }
        }

        // Регионы для Южного федерального округа
        if ($district->code === 'SFD') {
            $regions = [
                ['name' => 'Адыгея Республика', 'type' => 'republic', 'code' => '01', 'delivery_price' => 2300],
                ['name' => 'Волгоградская область', 'type' => 'oblast', 'code' => '34', 'delivery_price' => 2600],
                ['name' => 'Краснодарский край', 'type' => 'krai', 'code' => '23', 'delivery_price' => 2650],
            ];

            foreach ($regions as $regionData) {
                $region = Region::updateOrCreate(
                    [
                        'federal_district_id' => $district->id,
                        'code' => $regionData['code'],
                    ],
                    [
                        'name' => $regionData['name'],
                        'slug' => Str::slug($regionData['name']),
                        'type' => $regionData['type'],
                        'code' => $regionData['code'],
                        'delivery_price' => $regionData['delivery_price'] ?? null,
                        'is_active' => true,
                        'sort_order' => 0,
                    ]
                );

                $this->seedLocalitiesForRegion($region);
            }
        }
    }

    /**
     * Заполнить населенные пункты для региона
     */
    private function seedLocalitiesForRegion(Region $region): void
    {
        // Пример городов для Москвы
        if ($region->code === '77') {
            $localities = [
                ['name' => 'Москва', 'type' => 'city'],
            ];

            foreach ($localities as $localityData) {
                Locality::updateOrCreate(
                    [
                        'region_id' => $region->id,
                        'slug' => Str::slug($localityData['name']),
                    ],
                    [
                        'name' => $localityData['name'],
                        'type' => $localityData['type'],
                        'is_active' => true,
                        'sort_order' => 0,
                    ]
                );
            }
        }

        // Пример городов для Московской области
        if ($region->code === '50') {
            $localities = [
                ['name' => 'Москва', 'type' => 'city'],
                ['name' => 'Подольск', 'type' => 'city'],
                ['name' => 'Химки', 'type' => 'city'],
                ['name' => 'Мытищи', 'type' => 'city'],
            ];

            foreach ($localities as $localityData) {
                Locality::updateOrCreate(
                    [
                        'region_id' => $region->id,
                        'slug' => Str::slug($localityData['name']),
                    ],
                    [
                        'name' => $localityData['name'],
                        'type' => $localityData['type'],
                        'is_active' => true,
                        'sort_order' => 0,
                    ]
                );
            }
        }

        // Города и районы для Белгородской области
        if ($region->code === '31') {
            $localities = [
                ['name' => 'г. Белгород', 'type' => 'city', 'delivery_price' => 1500],
                ['name' => 'Алексеевский район', 'type' => 'district', 'delivery_price' => 1500],
                ['name' => 'Губкинский район', 'type' => 'district', 'delivery_price' => 1500],
                ['name' => 'Корочанский район', 'type' => 'district', 'delivery_price' => 1500],
                ['name' => 'Старооскольский городской округ', 'type' => 'urban_district', 'delivery_price' => 1500],
            ];

            foreach ($localities as $localityData) {
                Locality::updateOrCreate(
                    [
                        'region_id' => $region->id,
                        'slug' => Str::slug($localityData['name']),
                    ],
                    [
                        'name' => $localityData['name'],
                        'type' => $localityData['type'],
                        'delivery_price' => $localityData['delivery_price'] ?? null,
                        'is_active' => true,
                        'sort_order' => 0,
                    ]
                );
            }
        }

        // Города и районы для Воронежской области
        if ($region->code === '36') {
            $localities = [
                ['name' => 'Воронеж', 'type' => 'city', 'delivery_price' => 2100],
                ['name' => 'городской округ Нововоронеж', 'type' => 'urban_district', 'delivery_price' => 2100],
                ['name' => 'Верхнеландеховский район', 'type' => 'district', 'delivery_price' => 2100],
                ['name' => 'Вичугский район', 'type' => 'district', 'delivery_price' => 2100],
            ];

            foreach ($localities as $localityData) {
                Locality::updateOrCreate(
                    [
                        'region_id' => $region->id,
                        'slug' => Str::slug($localityData['name']),
                    ],
                    [
                        'name' => $localityData['name'],
                        'type' => $localityData['type'],
                        'delivery_price' => $localityData['delivery_price'] ?? null,
                        'is_active' => true,
                        'sort_order' => 0,
                    ]
                );
            }
        }

        // Города для Адыгеи
        if ($region->code === '01') {
            $localities = [
                ['name' => 'г. Адыгейск', 'type' => 'city', 'delivery_price' => 2300],
                ['name' => 'г. Майкоп', 'type' => 'city', 'delivery_price' => 2300],
            ];

            foreach ($localities as $localityData) {
                Locality::updateOrCreate(
                    [
                        'region_id' => $region->id,
                        'slug' => Str::slug($localityData['name']),
                    ],
                    [
                        'name' => $localityData['name'],
                        'type' => $localityData['type'],
                        'delivery_price' => $localityData['delivery_price'] ?? null,
                        'is_active' => true,
                        'sort_order' => 0,
                    ]
                );
            }
        }

        // Города для Волгоградской области
        if ($region->code === '34') {
            $localities = [
                ['name' => 'г. Волгоград', 'type' => 'city', 'delivery_price' => 2600],
                ['name' => 'г. Волжский', 'type' => 'city', 'delivery_price' => 2600],
            ];

            foreach ($localities as $localityData) {
                Locality::updateOrCreate(
                    [
                        'region_id' => $region->id,
                        'slug' => Str::slug($localityData['name']),
                    ],
                    [
                        'name' => $localityData['name'],
                        'type' => $localityData['type'],
                        'delivery_price' => $localityData['delivery_price'] ?? null,
                        'is_active' => true,
                        'sort_order' => 0,
                    ]
                );
            }
        }
    }
}
