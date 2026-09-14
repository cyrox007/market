<?php

namespace Database\Seeders;

/**
 * Демо-дерево комнат для локального тестирования (по образцу hoff).
 * Листья привязаны к продуктовым категориям, некоторым задан фильтр.
 */
class RoomsDemoSeeder extends RoomTreeSeeder
{
    protected function tree(): array
    {
        return [
            ['name' => 'Гостиная', 'slug' => 'gostinaya', 'children' => [
                ['name' => 'Диваны', 'slug' => 'gostinaya-divany', 'cats' => [1]],
                ['name' => 'Журнальные столики', 'slug' => 'gostinaya-stoliki', 'cats' => [14]],
                ['name' => 'Тумбы и комоды', 'slug' => 'gostinaya-hranenie', 'cats' => [16], 'filters' => ['price_min' => 3000]],
            ]],
            ['name' => 'Спальня', 'slug' => 'spalnya', 'children' => [
                ['name' => 'Кровати', 'slug' => 'spalnya-krovati', 'cats' => [4]],
                ['name' => 'Матрасы', 'slug' => 'spalnya-matrasy', 'cats' => [7]],
                ['name' => 'Шкафы', 'slug' => 'spalnya-shkafy', 'cats' => [8]],
            ]],
            ['name' => 'Детская', 'slug' => 'detskaya', 'children' => [
                ['name' => 'Кровати', 'slug' => 'detskaya-krovati', 'cats' => [6]],
                ['name' => 'Письменные столы', 'slug' => 'detskaya-stoly', 'cats' => [13]],
            ]],
            ['name' => 'Кухня', 'slug' => 'kuhnya', 'children' => [
                ['name' => 'Столы и стулья', 'slug' => 'kuhnya-stoly-stulya', 'cats' => [11, 15]],
            ]],
        ];
    }
}
