<?php

namespace Database\Seeders;

/**
 * Демонстрация наследования: 4 уровня вложенности, по несколько узлов на этаже.
 * Ветка «Квартира-студия» — усиление цены (20000 → 35000 → 50000) вниз по цепочке.
 * Ветка «Загородный дом» — сужение цветов ([3] → [2] → [1]).
 */
class RoomsDeepDemoSeeder extends RoomTreeSeeder
{
    protected function tree(): array
    {
        return [
            // Ветка усиления цены (пороги подобраны под реальные цены товаров).
            [
                'name' => 'Квартира-студия', 'slug' => 'studia',
                'filters' => ['price_min' => 20000],
                'children' => [
                    [
                        'name' => 'Зона гостиной', 'slug' => 'studia-gostinaya',
                        'filters' => ['price_min' => 35000], // усиление цены
                        'children' => [
                            [
                                'name' => 'Диваны', 'slug' => 'studia-gostinaya-divany',
                                'cats' => [1],
                                'children' => [
                                    [
                                        'name' => 'Прямые диваны', 'slug' => 'studia-gostinaya-divany-pryamye',
                                        'filters' => ['price_min' => 50000], // ещё усиление
                                        'cats' => [1],
                                    ],
                                    ['name' => 'Угловые диваны', 'slug' => 'studia-gostinaya-divany-uglovye', 'cats' => [1]],
                                ],
                            ],
                            ['name' => 'Кресла', 'slug' => 'studia-gostinaya-kresla', 'cats' => [1]],
                        ],
                    ],
                    [
                        'name' => 'Зона столовой', 'slug' => 'studia-stolovaya',
                        'filters' => ['price_min' => 25000],
                        'children' => [
                            ['name' => 'Столы', 'slug' => 'studia-stolovaya-stoly', 'cats' => [11]],
                            ['name' => 'Стулья', 'slug' => 'studia-stolovaya-stulya', 'cats' => [15]],
                        ],
                    ],
                ],
            ],
            // Ветка сужения цветов.
            [
                'name' => 'Загородный дом', 'slug' => 'zagorodnyi',
                'filters' => ['colors' => ['seryi', 'temno-sinii', 'bezevyi']],
                'children' => [
                    [
                        'name' => 'Гостиная', 'slug' => 'zagorodnyi-gostinaya',
                        'filters' => ['colors' => ['seryi', 'temno-sinii']], // сужение цветов
                        'children' => [
                            [
                                'name' => 'Диваны', 'slug' => 'zagorodnyi-gostinaya-divany',
                                'filters' => ['colors' => ['seryi']], // ещё сужение
                                'cats' => [1],
                            ],
                            ['name' => 'Кресла', 'slug' => 'zagorodnyi-gostinaya-kresla', 'cats' => [1]],
                        ],
                    ],
                    ['name' => 'Спальня', 'slug' => 'zagorodnyi-spalnya', 'cats' => [4]],
                ],
            ],
        ];
    }
}
