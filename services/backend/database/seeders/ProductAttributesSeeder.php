<?php

namespace Database\Seeders;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use Illuminate\Database\Seeder;

class ProductAttributesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Атрибуты с is_use_in_variations используются в торговых предложениях (вариациях).
     */
    public function run(): void
    {
        // Цвет — участвует в вариациях
        $colorAttribute = Attribute::firstOrCreate(
            ['slug' => 'color'],
            [
                'name' => 'Цвет',
                'type' => 'color',
                'is_filterable' => true,
                'is_use_in_variations' => true,
                'sort_order' => 1,
            ]
        );
        $colorAttribute->update(['is_use_in_variations' => true]);

        $colors = [
            ['value' => 'Серый', 'slug' => 'gray', 'color_code' => '#808080', 'sort_order' => 1],
            ['value' => 'Бежевый', 'slug' => 'beige', 'color_code' => '#D2B48C', 'sort_order' => 2],
            ['value' => 'Темно-синий', 'slug' => 'dark-blue', 'color_code' => '#000080', 'sort_order' => 3],
            ['value' => 'Коричневый', 'slug' => 'brown', 'color_code' => '#8B4513', 'sort_order' => 4],
            ['value' => 'Белая', 'slug' => 'white-f', 'color_code' => '#FFFFFF', 'sort_order' => 5],
            ['value' => 'Серая', 'slug' => 'gray-f', 'color_code' => '#808080', 'sort_order' => 6],
            ['value' => 'Бежевая', 'slug' => 'beige-f', 'color_code' => '#F5F5DC', 'sort_order' => 7],
            ['value' => 'Коричневая', 'slug' => 'brown-f', 'color_code' => '#8B4513', 'sort_order' => 8],
            ['value' => 'Темно-коричневая', 'slug' => 'dark-brown-f', 'color_code' => '#654321', 'sort_order' => 9],
            ['value' => 'Черная', 'slug' => 'black-f', 'color_code' => '#000000', 'sort_order' => 10],
            ['value' => 'Натуральное дерево', 'slug' => 'natural-wood', 'color_code' => '#8B4513', 'sort_order' => 11],
            ['value' => 'Белый глянец', 'slug' => 'white-gloss', 'color_code' => '#FFFFFF', 'sort_order' => 12],
            ['value' => 'Белый', 'slug' => 'white', 'color_code' => '#FFFFFF', 'sort_order' => 13],
            ['value' => 'Черный', 'slug' => 'black', 'color_code' => '#000000', 'sort_order' => 14],
            ['value' => 'Синий', 'slug' => 'blue', 'color_code' => '#4169E1', 'sort_order' => 15],
            ['value' => 'Красный', 'slug' => 'red', 'color_code' => '#FF0000', 'sort_order' => 16],
            ['value' => 'Зеленый', 'slug' => 'green', 'color_code' => '#008000', 'sort_order' => 17],
        ];

        foreach ($colors as $color) {
            AttributeValue::firstOrCreate(
                [
                    'attribute_id' => $colorAttribute->id,
                    'slug' => $color['slug'],
                ],
                $color
            );
        }

        // Размер — участвует в вариациях (slug для сопоставления: 180x95, 240x150 и т.д.)
        $sizeAttribute = Attribute::firstOrCreate(
            ['slug' => 'size'],
            [
                'name' => 'Размер',
                'type' => 'select',
                'is_filterable' => true,
                'is_use_in_variations' => true,
                'sort_order' => 2,
            ]
        );
        $sizeAttribute->update(['is_use_in_variations' => true]);

        $sizes = [
            ['value' => '180 × 95 см', 'slug' => '180x95', 'sort_order' => 1],
            ['value' => '210 × 95 см', 'slug' => '210x95', 'sort_order' => 2],
            ['value' => '240 × 95 см', 'slug' => '240x95', 'sort_order' => 3],
            ['value' => '240 × 150 см', 'slug' => '240x150', 'sort_order' => 4],
            ['value' => '280 × 180 см', 'slug' => '280x180', 'sort_order' => 5],
            ['value' => '320 × 200 см', 'slug' => '320x200', 'sort_order' => 6],
            ['value' => '200 × 160 см', 'slug' => '200x160', 'sort_order' => 7],
            ['value' => '200 × 90 см', 'slug' => '200x90', 'sort_order' => 8],
            ['value' => '200 × 120 см', 'slug' => '200x120', 'sort_order' => 9],
            ['value' => '120 × 80 см', 'slug' => '120x80', 'sort_order' => 10],
            ['value' => '150 × 90 см', 'slug' => '150x90', 'sort_order' => 11],
            ['value' => '180 × 90 см', 'slug' => '180x90', 'sort_order' => 12],
            ['value' => '200 × 100 см', 'slug' => '200x100', 'sort_order' => 13],
            ['value' => '85 см', 'slug' => '85', 'sort_order' => 14],
            ['value' => '95 см', 'slug' => '95', 'sort_order' => 15],
            ['value' => '150 × 60 × 220 см', 'slug' => '150x60x220', 'sort_order' => 16],
            ['value' => '200 × 60 × 220 см', 'slug' => '200x60x220', 'sort_order' => 17],
            ['value' => '250 × 60 × 220 см', 'slug' => '250x60x220', 'sort_order' => 18],
            ['value' => '300 × 60 × 220 см', 'slug' => '300x60x220', 'sort_order' => 19],
        ];

        foreach ($sizes as $size) {
            AttributeValue::firstOrCreate(
                [
                    'attribute_id' => $sizeAttribute->id,
                    'slug' => $size['slug'],
                ],
                array_merge($size, ['color_code' => null])
            );
        }

        // Комплект — для будущих товаров с вариациями по комплекту
        $komplektAttribute = Attribute::firstOrCreate(
            ['slug' => 'komplekt'],
            [
                'name' => 'Комплект',
                'type' => 'select',
                'is_filterable' => false,
                'is_use_in_variations' => true,
                'sort_order' => 3,
            ]
        );
        $komplektAttribute->update(['is_use_in_variations' => true]);

        $komplekts = [
            ['value' => 'Базовый', 'slug' => 'base', 'sort_order' => 1],
            ['value' => 'Расширенный', 'slug' => 'extended', 'sort_order' => 2],
            ['value' => 'Полный', 'slug' => 'full', 'sort_order' => 3],
        ];
        foreach ($komplekts as $k) {
            AttributeValue::firstOrCreate(
                [
                    'attribute_id' => $komplektAttribute->id,
                    'slug' => $k['slug'],
                ],
                array_merge($k, ['color_code' => null])
            );
        }

        // Вариант — для вариаций с произвольным значением (ручной ввод или из импорта опций)
        $variantAttribute = Attribute::firstOrCreate(
            ['slug' => \App\Models\Product\Attribute::SLUG_VARIANT],
            [
                'name' => 'Вариант',
                'type' => 'string',
                'is_filterable' => false,
                'is_use_in_variations' => true,
                'allow_custom_value' => true,
                'sort_order' => 4,
            ]
        );
        $variantAttribute->update(['is_use_in_variations' => true, 'allow_custom_value' => true]);

        // Модель — из выгрузки OpenCart (столбец model)
        $modelAttribute = Attribute::firstOrCreate(
            ['slug' => 'model'],
            [
                'name' => 'Модель',
                'type' => 'string',
                'is_filterable' => false,
                'is_use_in_variations' => false,
                'allow_custom_value' => true,
                'sort_order' => 5,
            ]
        );
        $modelAttribute->update(['allow_custom_value' => true]);

        // Производитель — из выгрузки OpenCart (столбец manufacturer), фильтруемый
        $manufacturerAttribute = Attribute::firstOrCreate(
            ['slug' => Attribute::SLUG_MANUFACTURER],
            [
                'name' => 'Производитель',
                'type' => 'string',
                'is_filterable' => true,
                'is_use_in_variations' => false,
                'allow_custom_value' => true,
                'sort_order' => 6,
            ]
        );
        $manufacturerAttribute->update(['is_filterable' => true, 'allow_custom_value' => true]);
    }
}
