<?php

namespace Database\Seeders;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use App\Models\Product\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Vanilo\Category\Models\Taxonomy;
use Illuminate\Support\Facades\File;

class ProductVariantsSeeder extends Seeder
{
    /**
     * Массив доступных изображений
     */
    private array $availableImages = [];

    /**
     * Заполнение товаров с разными вариантами:
     * - Обычные товары (без вариаций, с цветом и размером)
     * - Вариативные товары (с вариациями по цветам и размерам)
     * - Товары с цветами и без
     * - Товары с фото из локальных папок
     */
    public function run(): void
    {
        // Загружаем все доступные изображения
        $this->loadAvailableImages();

        $taxonomyId = $this->getProductCategoriesTaxonomyId();

        // Получаем категории
        $sofasStraight = Category::where('slug', 'pryamye-divany')->first();
        $sofasCorner = Category::where('slug', 'uglovye-divany')->first();
        $bedsDouble = Category::where('slug', 'dvuspalnye-krovati')->first();
        $bedsSingle = Category::where('slug', 'odnospalnye-krovati')->first();
        $tablesDining = Category::where('slug', 'obedennye-stoly')->first();
        $tablesCoffee = Category::where('slug', 'zhurnalnye-stoly')->first();
        $chairs = Category::where('slug', 'stulya')->first();
        $armchairs = Category::where('slug', 'kresla')->first();
        $wardrobes = Category::where('slug', 'shkafy')->first();

        $totalVariants = 0;

        // ============================================
        // 1. ВАРИАТИВНЫЙ ДИВАН ПРЯМОЙ (цвета + размеры)
        // ============================================
        $sofa1 = Product::updateOrCreate(
            ['sku' => 'SV-VAR-SOFA-001'],
            [
                'name' => 'Диван прямой «Премиум Комфорт»',
                'slug' => Str::slug('Диван прямой Премиум Комфорт'),
                'price' => 34990,
                'original_price' => 42990,
                'state' => 'active',
                'description' => 'Роскошный прямой диван с множеством вариантов цветов и размеров. Изготовлен из высококачественных материалов, обеспечивает максимальный комфорт. Идеально подходит для гостиной любого стиля.',
                'excerpt' => 'Прямой диван премиум-класса с выбором цвета и размера.',
                'stock' => 0,
                'backorder' => false,
                'units_sold' => 0,
                'is_variable' => true,
            ]
        );

        if ($sofasStraight) {
            $sofa1->taxons()->syncWithoutDetaching([$sofasStraight->id]);
        }

        $this->addRandomImage($sofa1, 'images');
        $this->addRandomImage($sofa1, 'gallery');
        $this->addRandomImage($sofa1, 'gallery');

        $sofa1Variants = [
            ['color' => 'Серый', 'color_code' => '#808080', 'length' => 180, 'price' => 34990, 'original_price' => 42990, 'stock' => 5],
            ['color' => 'Серый', 'color_code' => '#808080', 'length' => 210, 'price' => 37990, 'original_price' => 45990, 'stock' => 3],
            ['color' => 'Серый', 'color_code' => '#808080', 'length' => 240, 'price' => 40990, 'original_price' => 48990, 'stock' => 2],
            ['color' => 'Бежевый', 'color_code' => '#F5F5DC', 'length' => 180, 'price' => 34990, 'original_price' => 42990, 'stock' => 4],
            ['color' => 'Бежевый', 'color_code' => '#F5F5DC', 'length' => 210, 'price' => 37990, 'original_price' => 45990, 'stock' => 3],
            ['color' => 'Бежевый', 'color_code' => '#F5F5DC', 'length' => 240, 'price' => 40990, 'original_price' => 48990, 'stock' => 1],
            ['color' => 'Темно-синий', 'color_code' => '#000080', 'length' => 180, 'price' => 36990, 'original_price' => 44990, 'stock' => 6],
            ['color' => 'Темно-синий', 'color_code' => '#000080', 'length' => 210, 'price' => 39990, 'original_price' => 47990, 'stock' => 4],
            ['color' => 'Темно-синий', 'color_code' => '#000080', 'length' => 240, 'price' => 42990, 'original_price' => 50990, 'stock' => 2],
            ['color' => 'Коричневый', 'color_code' => '#8B4513', 'length' => 180, 'price' => 35990, 'original_price' => 43990, 'stock' => 3],
            ['color' => 'Коричневый', 'color_code' => '#8B4513', 'length' => 210, 'price' => 38990, 'original_price' => 46990, 'stock' => 2],
            ['color' => 'Коричневый', 'color_code' => '#8B4513', 'length' => 240, 'price' => 41990, 'original_price' => 49990, 'stock' => 1],
        ];

        foreach ($sofa1Variants as $idx => $variantData) {
            $variant = Product::updateOrCreate(
                ['sku' => 'SV-VAR-SOFA-001-' . strtoupper(substr(Str::slug($variantData['color']), 0, 3)) . '-' . $variantData['length']],
                [
                    'name' => $sofa1->name . ' (' . $variantData['color'] . ', ' . $variantData['length'] . ' см)',
                    'slug' => Str::slug($sofa1->name . ' ' . $variantData['color'] . ' ' . $variantData['length']),
                    'price' => $variantData['price'],
                    'original_price' => $variantData['original_price'],
                    'state' => 'active',
                    'description' => $sofa1->description,
                    'excerpt' => $sofa1->excerpt,
                    'stock' => $variantData['stock'],
                    'backorder' => false,
                    'units_sold' => 0,
                    'is_variable' => false,
                    'parent_product_id' => $sofa1->id,
                    'color' => $variantData['color'],
                    'color_code' => $variantData['color_code'],
                    'length' => $variantData['length'],
                    'width' => 95,
                    'height' => 88,
                    'weight' => 45 + ($variantData['length'] - 180) / 30 * 7,
                ]
            );
            $this->syncVariantAttributes($variant, $variantData['color'] ?? null, $variantData['length'] ?? null, 95, null);
            $this->addRandomImage($variant, 'images');
            $totalVariants++;
        }

        // ============================================
        // 2. ВАРИАТИВНЫЙ УГЛОВОЙ ДИВАН (цвета + размеры)
        // ============================================
        $sofaCorner1 = Product::updateOrCreate(
            ['sku' => 'SV-VAR-CORNER-001'],
            [
                'name' => 'Угловой диван «Модуль Премиум»',
                'slug' => Str::slug('Угловой диван Модуль Премиум'),
                'price' => 54990,
                'original_price' => 64990,
                'state' => 'active',
                'description' => 'Современный угловой диван с модульной конструкцией. Большой выбор цветов и размеров позволяет подобрать идеальный вариант для вашей гостиной. Высокое качество материалов и сборки.',
                'excerpt' => 'Модульный угловой диван с выбором цвета и размера.',
                'stock' => 0,
                'backorder' => false,
                'units_sold' => 0,
                'is_variable' => true,
            ]
        );

        if ($sofasCorner) {
            $sofaCorner1->taxons()->syncWithoutDetaching([$sofasCorner->id]);
        }

        $this->addRandomImage($sofaCorner1, 'images');
        $this->addRandomImage($sofaCorner1, 'gallery');
        $this->addRandomImage($sofaCorner1, 'gallery');
        $this->addRandomImage($sofaCorner1, 'gallery');

        $sofaCorner1Variants = [
            ['color' => 'Серый', 'color_code' => '#808080', 'length' => 240, 'width' => 150, 'price' => 54990, 'original_price' => 64990, 'stock' => 3],
            ['color' => 'Серый', 'color_code' => '#808080', 'length' => 280, 'width' => 180, 'price' => 64990, 'original_price' => 74990, 'stock' => 2],
            ['color' => 'Серый', 'color_code' => '#808080', 'length' => 320, 'width' => 200, 'price' => 74990, 'original_price' => 84990, 'stock' => 1],
            ['color' => 'Бежевый', 'color_code' => '#F5F5DC', 'length' => 240, 'width' => 150, 'price' => 54990, 'original_price' => 64990, 'stock' => 2],
            ['color' => 'Бежевый', 'color_code' => '#F5F5DC', 'length' => 280, 'width' => 180, 'price' => 64990, 'original_price' => 74990, 'stock' => 1],
            ['color' => 'Бежевый', 'color_code' => '#F5F5DC', 'length' => 320, 'width' => 200, 'price' => 74990, 'original_price' => 84990, 'stock' => 1],
            ['color' => 'Темно-синий', 'color_code' => '#000080', 'length' => 240, 'width' => 150, 'price' => 56990, 'original_price' => 66990, 'stock' => 4],
            ['color' => 'Темно-синий', 'color_code' => '#000080', 'length' => 280, 'width' => 180, 'price' => 66990, 'original_price' => 76990, 'stock' => 2],
            ['color' => 'Темно-синий', 'color_code' => '#000080', 'length' => 320, 'width' => 200, 'price' => 76990, 'original_price' => 86990, 'stock' => 1],
            ['color' => 'Коричневый', 'color_code' => '#8B4513', 'length' => 240, 'width' => 150, 'price' => 55990, 'original_price' => 65990, 'stock' => 2],
            ['color' => 'Коричневый', 'color_code' => '#8B4513', 'length' => 280, 'width' => 180, 'price' => 65990, 'original_price' => 75990, 'stock' => 1],
        ];

        foreach ($sofaCorner1Variants as $variantData) {
            $variant = Product::updateOrCreate(
                ['sku' => 'SV-VAR-CORNER-001-' . strtoupper(substr(Str::slug($variantData['color']), 0, 3)) . '-' . $variantData['length']],
                [
                    'name' => $sofaCorner1->name . ' (' . $variantData['color'] . ', ' . $variantData['length'] . '×' . $variantData['width'] . ' см)',
                    'slug' => Str::slug($sofaCorner1->name . ' ' . $variantData['color'] . ' ' . $variantData['length']),
                    'price' => $variantData['price'],
                    'original_price' => $variantData['original_price'],
                    'state' => 'active',
                    'description' => $sofaCorner1->description,
                    'excerpt' => $sofaCorner1->excerpt,
                    'stock' => $variantData['stock'],
                    'backorder' => false,
                    'units_sold' => 0,
                    'is_variable' => false,
                    'parent_product_id' => $sofaCorner1->id,
                    'color' => $variantData['color'],
                    'color_code' => $variantData['color_code'],
                    'length' => $variantData['length'],
                    'width' => $variantData['width'],
                    'height' => 90,
                    'weight' => 70 + ($variantData['length'] - 240) / 40 * 15,
                ]
            );
            $this->syncVariantAttributes($variant, $variantData['color'] ?? null, $variantData['length'] ?? null, $variantData['width'] ?? null, 90);
            $this->addRandomImage($variant, 'images');
            $totalVariants++;
        }

        // ============================================
        // 3. ВАРИАТИВНАЯ КРОВАТЬ (только цвета)
        // ============================================
        $bed1 = Product::updateOrCreate(
            ['sku' => 'SV-VAR-BED-001'],
            [
                'name' => 'Кровать двуспальная «Элегант Премиум»',
                'slug' => Str::slug('Кровать двуспальная Элегант Премиум'),
                'price' => 44990,
                'original_price' => 54990,
                'state' => 'active',
                'description' => 'Элегантная двуспальная кровать премиум-класса с выбором цвета обивки. Размер фиксированный: 160×200 см. Изготовлена из массива дерева и высококачественной обивки.',
                'excerpt' => 'Двуспальная кровать премиум-класса с выбором цвета.',
                'stock' => 0,
                'backorder' => false,
                'units_sold' => 0,
                'is_variable' => true,
            ]
        );

        if ($bedsDouble) {
            $bed1->taxons()->syncWithoutDetaching([$bedsDouble->id]);
        }

        $this->addRandomImage($bed1, 'images');
        $this->addRandomImage($bed1, 'gallery');
        $this->addRandomImage($bed1, 'gallery');

        $bed1Variants = [
            ['color' => 'Белая', 'color_code' => '#FFFFFF', 'price' => 44990, 'original_price' => 54990, 'stock' => 5],
            ['color' => 'Серая', 'color_code' => '#808080', 'price' => 44990, 'original_price' => 54990, 'stock' => 4],
            ['color' => 'Бежевая', 'color_code' => '#F5F5DC', 'price' => 44990, 'original_price' => 54990, 'stock' => 3],
            ['color' => 'Коричневая', 'color_code' => '#8B4513', 'price' => 45990, 'original_price' => 55990, 'stock' => 4],
            ['color' => 'Темно-коричневая', 'color_code' => '#654321', 'price' => 45990, 'original_price' => 55990, 'stock' => 2],
            ['color' => 'Черная', 'color_code' => '#000000', 'price' => 46990, 'original_price' => 56990, 'stock' => 3],
        ];

        foreach ($bed1Variants as $variantData) {
            $variant = Product::updateOrCreate(
                ['sku' => 'SV-VAR-BED-001-' . strtoupper(substr(Str::slug($variantData['color']), 0, 3))],
                [
                    'name' => $bed1->name . ' (' . $variantData['color'] . ')',
                    'slug' => Str::slug($bed1->name . ' ' . $variantData['color']),
                    'price' => $variantData['price'],
                    'original_price' => $variantData['original_price'],
                    'state' => 'active',
                    'description' => $bed1->description,
                    'excerpt' => $bed1->excerpt,
                    'stock' => $variantData['stock'],
                    'backorder' => false,
                    'units_sold' => 0,
                    'is_variable' => false,
                    'parent_product_id' => $bed1->id,
                    'color' => $variantData['color'],
                    'color_code' => $variantData['color_code'],
                    'length' => 200,
                    'width' => 160,
                    'height' => 110,
                    'weight' => 65,
                ]
            );
            $this->syncVariantAttributes($variant, $variantData['color'] ?? null, 200, 160, 110);
            $this->addRandomImage($variant, 'images');
            $totalVariants++;
        }

        // ============================================
        // 4. ВАРИАТИВНАЯ КРОВАТЬ ОДНОСПАЛЬНАЯ (цвета + размеры)
        // ============================================
        $bedSingle1 = Product::updateOrCreate(
            ['sku' => 'SV-VAR-BED-SINGLE-001'],
            [
                'name' => 'Кровать односпальная «Комфорт»',
                'slug' => Str::slug('Кровать односпальная Комфорт'),
                'price' => 24990,
                'original_price' => 29990,
                'state' => 'active',
                'description' => 'Удобная односпальная кровать с выбором размера и цвета. Подходит для детской комнаты или гостевой спальни. Качественные материалы и надежная конструкция.',
                'excerpt' => 'Односпальная кровать с выбором размера и цвета.',
                'stock' => 0,
                'backorder' => false,
                'units_sold' => 0,
                'is_variable' => true,
            ]
        );

        if ($bedsSingle) {
            $bedSingle1->taxons()->syncWithoutDetaching([$bedsSingle->id]);
        }

        $this->addRandomImage($bedSingle1, 'images');
        $this->addRandomImage($bedSingle1, 'gallery');

        $bedSingle1Variants = [
            ['color' => 'Белая', 'color_code' => '#FFFFFF', 'length' => 200, 'width' => 90, 'price' => 24990, 'original_price' => 29990, 'stock' => 4],
            ['color' => 'Белая', 'color_code' => '#FFFFFF', 'length' => 200, 'width' => 120, 'price' => 27990, 'original_price' => 32990, 'stock' => 3],
            ['color' => 'Серая', 'color_code' => '#808080', 'length' => 200, 'width' => 90, 'price' => 24990, 'original_price' => 29990, 'stock' => 3],
            ['color' => 'Серая', 'color_code' => '#808080', 'length' => 200, 'width' => 120, 'price' => 27990, 'original_price' => 32990, 'stock' => 2],
            ['color' => 'Бежевая', 'color_code' => '#F5F5DC', 'length' => 200, 'width' => 90, 'price' => 24990, 'original_price' => 29990, 'stock' => 2],
            ['color' => 'Бежевая', 'color_code' => '#F5F5DC', 'length' => 200, 'width' => 120, 'price' => 27990, 'original_price' => 32990, 'stock' => 1],
        ];

        foreach ($bedSingle1Variants as $variantData) {
            $variant = Product::updateOrCreate(
                ['sku' => 'SV-VAR-BED-SINGLE-001-' . strtoupper(substr(Str::slug($variantData['color']), 0, 3)) . '-' . $variantData['width']],
                [
                    'name' => $bedSingle1->name . ' (' . $variantData['color'] . ', ' . $variantData['length'] . '×' . $variantData['width'] . ' см)',
                    'slug' => Str::slug($bedSingle1->name . ' ' . $variantData['color'] . ' ' . $variantData['width']),
                    'price' => $variantData['price'],
                    'original_price' => $variantData['original_price'],
                    'state' => 'active',
                    'description' => $bedSingle1->description,
                    'excerpt' => $bedSingle1->excerpt,
                    'stock' => $variantData['stock'],
                    'backorder' => false,
                    'units_sold' => 0,
                    'is_variable' => false,
                    'parent_product_id' => $bedSingle1->id,
                    'color' => $variantData['color'],
                    'color_code' => $variantData['color_code'],
                    'length' => $variantData['length'],
                    'width' => $variantData['width'],
                    'height' => 100,
                    'weight' => 45,
                ]
            );
            $this->syncVariantAttributes($variant, $variantData['color'] ?? null, $variantData['length'] ?? null, $variantData['width'] ?? null, 100);
            $this->addRandomImage($variant, 'images');
            $totalVariants++;
        }

        // ============================================
        // 5. ВАРИАТИВНЫЙ СТОЛ ОБЕДЕННЫЙ (только размеры)
        // ============================================
        $table1 = Product::updateOrCreate(
            ['sku' => 'SV-VAR-TABLE-001'],
            [
                'name' => 'Стол обеденный «Классик Премиум»',
                'slug' => Str::slug('Стол обеденный Классик Премиум'),
                'price' => 19990,
                'original_price' => 24990,
                'state' => 'active',
                'description' => 'Классический обеденный стол из массива дерева с выбором размера. Прочный, долговечный, подходит для семейных обедов и праздников. Один цвет - натуральное дерево.',
                'excerpt' => 'Обеденный стол из массива дерева с выбором размера.',
                'stock' => 0,
                'backorder' => false,
                'units_sold' => 0,
                'is_variable' => true,
            ]
        );

        if ($tablesDining) {
            $table1->taxons()->syncWithoutDetaching([$tablesDining->id]);
        }

        $this->addRandomImage($table1, 'images');
        $this->addRandomImage($table1, 'gallery');

        $table1Variants = [
            ['length' => 120, 'width' => 80, 'price' => 19990, 'original_price' => 24990, 'stock' => 5],
            ['length' => 150, 'width' => 90, 'price' => 23990, 'original_price' => 28990, 'stock' => 3],
            ['length' => 180, 'width' => 90, 'price' => 27990, 'original_price' => 32990, 'stock' => 2],
            ['length' => 200, 'width' => 100, 'price' => 31990, 'original_price' => 36990, 'stock' => 1],
        ];

        foreach ($table1Variants as $variantData) {
            $variant = Product::updateOrCreate(
                ['sku' => 'SV-VAR-TABLE-001-' . $variantData['length'] . 'x' . $variantData['width']],
                [
                    'name' => $table1->name . ' (' . $variantData['length'] . '×' . $variantData['width'] . ' см)',
                    'slug' => Str::slug($table1->name . ' ' . $variantData['length'] . ' ' . $variantData['width']),
                    'price' => $variantData['price'],
                    'original_price' => $variantData['original_price'],
                    'state' => 'active',
                    'description' => $table1->description,
                    'excerpt' => $table1->excerpt,
                    'stock' => $variantData['stock'],
                    'backorder' => false,
                    'units_sold' => 0,
                    'is_variable' => false,
                    'parent_product_id' => $table1->id,
                    'color' => 'Натуральное дерево',
                    'color_code' => '#8B4513',
                    'length' => $variantData['length'],
                    'width' => $variantData['width'],
                    'height' => 75,
                    'weight' => 20 + ($variantData['length'] - 120) / 30 * 5,
                ]
            );
            $this->syncVariantAttributes($variant, 'Натуральное дерево', $variantData['length'], $variantData['width'], 75);
            $this->addRandomImage($variant, 'images');
            $totalVariants++;
        }

        // ============================================
        // 6. ВАРИАТИВНЫЕ СТУЛЬЯ (цвета)
        // ============================================
        $chair1 = Product::updateOrCreate(
            ['sku' => 'SV-VAR-CHAIR-001'],
            [
                'name' => 'Стул кухонный «Эргономик»',
                'slug' => Str::slug('Стул кухонный Эргономик'),
                'price' => 4490,
                'original_price' => 5490,
                'state' => 'active',
                'description' => 'Эргономичный кухонный стул с выбором цвета. Удобная спинка, прочная конструкция, легко моется. Размер фиксированный: 45×45×85 см.',
                'excerpt' => 'Кухонный стул с выбором цвета.',
                'stock' => 0,
                'backorder' => false,
                'units_sold' => 0,
                'is_variable' => true,
            ]
        );

        if ($chairs) {
            $chair1->taxons()->syncWithoutDetaching([$chairs->id]);
        }

        $this->addRandomImage($chair1, 'images');
        $this->addRandomImage($chair1, 'gallery');

        $chair1Variants = [
            ['color' => 'Белый', 'color_code' => '#FFFFFF', 'price' => 4490, 'original_price' => 5490, 'stock' => 15],
            ['color' => 'Черный', 'color_code' => '#000000', 'price' => 4490, 'original_price' => 5490, 'stock' => 12],
            ['color' => 'Серый', 'color_code' => '#808080', 'price' => 4490, 'original_price' => 5490, 'stock' => 10],
            ['color' => 'Коричневый', 'color_code' => '#8B4513', 'price' => 4590, 'original_price' => 5590, 'stock' => 8],
            ['color' => 'Бежевый', 'color_code' => '#F5F5DC', 'price' => 4490, 'original_price' => 5490, 'stock' => 9],
        ];

        foreach ($chair1Variants as $variantData) {
            $variant = Product::updateOrCreate(
                ['sku' => 'SV-VAR-CHAIR-001-' . strtoupper(substr(Str::slug($variantData['color']), 0, 3))],
                [
                    'name' => $chair1->name . ' (' . $variantData['color'] . ')',
                    'slug' => Str::slug($chair1->name . ' ' . $variantData['color']),
                    'price' => $variantData['price'],
                    'original_price' => $variantData['original_price'],
                    'state' => 'active',
                    'description' => $chair1->description,
                    'excerpt' => $chair1->excerpt,
                    'stock' => $variantData['stock'],
                    'backorder' => false,
                    'units_sold' => 0,
                    'is_variable' => false,
                    'parent_product_id' => $chair1->id,
                    'color' => $variantData['color'],
                    'color_code' => $variantData['color_code'],
                    'length' => 45,
                    'width' => 45,
                    'height' => 85,
                    'weight' => 3.5,
                ]
            );
            $this->syncVariantAttributes($variant, $variantData['color'] ?? null, 45, 45, 85);
            $this->addRandomImage($variant, 'images');
            $totalVariants++;
        }

        // ============================================
        // 7. ВАРИАТИВНОЕ КРЕСЛО (цвета + размеры)
        // ============================================
        $armchair1 = Product::updateOrCreate(
            ['sku' => 'SV-VAR-ARMCHAIR-001'],
            [
                'name' => 'Кресло «Релакс Премиум»',
                'slug' => Str::slug('Кресло Релакс Премиум'),
                'price' => 19990,
                'original_price' => 24990,
                'state' => 'active',
                'description' => 'Комфортное кресло премиум-класса с выбором цвета и размера. Идеально для отдыха и чтения. Высококачественная обивка и наполнитель.',
                'excerpt' => 'Кресло премиум-класса с выбором цвета и размера.',
                'stock' => 0,
                'backorder' => false,
                'units_sold' => 0,
                'is_variable' => true,
            ]
        );

        if ($armchairs) {
            $armchair1->taxons()->syncWithoutDetaching([$armchairs->id]);
        }

        $this->addRandomImage($armchair1, 'images');
        $this->addRandomImage($armchair1, 'gallery');
        $this->addRandomImage($armchair1, 'gallery');

        $armchair1Variants = [
            ['color' => 'Серый', 'color_code' => '#808080', 'width' => 85, 'price' => 19990, 'original_price' => 24990, 'stock' => 4],
            ['color' => 'Серый', 'color_code' => '#808080', 'width' => 95, 'price' => 21990, 'original_price' => 26990, 'stock' => 3],
            ['color' => 'Бежевый', 'color_code' => '#F5F5DC', 'width' => 85, 'price' => 19990, 'original_price' => 24990, 'stock' => 3],
            ['color' => 'Бежевый', 'color_code' => '#F5F5DC', 'width' => 95, 'price' => 21990, 'original_price' => 26990, 'stock' => 2],
            ['color' => 'Коричневый', 'color_code' => '#8B4513', 'width' => 85, 'price' => 20990, 'original_price' => 25990, 'stock' => 2],
            ['color' => 'Коричневый', 'color_code' => '#8B4513', 'width' => 95, 'price' => 22990, 'original_price' => 27990, 'stock' => 1],
            ['color' => 'Темно-синий', 'color_code' => '#000080', 'width' => 85, 'price' => 20990, 'original_price' => 25990, 'stock' => 3],
            ['color' => 'Темно-синий', 'color_code' => '#000080', 'width' => 95, 'price' => 22990, 'original_price' => 27990, 'stock' => 2],
        ];

        foreach ($armchair1Variants as $variantData) {
            $variant = Product::updateOrCreate(
                ['sku' => 'SV-VAR-ARMCHAIR-001-' . strtoupper(substr(Str::slug($variantData['color']), 0, 3)) . '-' . $variantData['width']],
                [
                    'name' => $armchair1->name . ' (' . $variantData['color'] . ', ' . $variantData['width'] . ' см)',
                    'slug' => Str::slug($armchair1->name . ' ' . $variantData['color'] . ' ' . $variantData['width']),
                    'price' => $variantData['price'],
                    'original_price' => $variantData['original_price'],
                    'state' => 'active',
                    'description' => $armchair1->description,
                    'excerpt' => $armchair1->excerpt,
                    'stock' => $variantData['stock'],
                    'backorder' => false,
                    'units_sold' => 0,
                    'is_variable' => false,
                    'parent_product_id' => $armchair1->id,
                    'color' => $variantData['color'],
                    'color_code' => $variantData['color_code'],
                    'length' => 90,
                    'width' => $variantData['width'],
                    'height' => 95,
                    'weight' => 18,
                ]
            );
            $this->syncVariantAttributes($variant, $variantData['color'] ?? null, 90, $variantData['width'] ?? null, 95);
            $this->addRandomImage($variant, 'images');
            $totalVariants++;
        }

        // ============================================
        // 8. ВАРИАТИВНЫЙ ШКАФ (только размеры)
        // ============================================
        $wardrobe1 = Product::updateOrCreate(
            ['sku' => 'SV-VAR-WARDROBE-001'],
            [
                'name' => 'Шкаф-купе «Стандарт»',
                'slug' => Str::slug('Шкаф-купе Стандарт'),
                'price' => 39990,
                'original_price' => 49990,
                'state' => 'active',
                'description' => 'Вместительный шкаф-купе с выбором размера. Один цвет - белый глянец. Современный дизайн, качественная фурнитура, раздвижные двери.',
                'excerpt' => 'Шкаф-купе с выбором размера.',
                'stock' => 0,
                'backorder' => false,
                'units_sold' => 0,
                'is_variable' => true,
            ]
        );

        if ($wardrobes) {
            $wardrobe1->taxons()->syncWithoutDetaching([$wardrobes->id]);
        }

        $this->addRandomImage($wardrobe1, 'images');
        $this->addRandomImage($wardrobe1, 'gallery');

        $wardrobe1Variants = [
            ['length' => 150, 'width' => 60, 'height' => 220, 'price' => 39990, 'original_price' => 49990, 'stock' => 3],
            ['length' => 200, 'width' => 60, 'height' => 220, 'price' => 49990, 'original_price' => 59990, 'stock' => 2],
            ['length' => 250, 'width' => 60, 'height' => 220, 'price' => 59990, 'original_price' => 69990, 'stock' => 1],
            ['length' => 300, 'width' => 60, 'height' => 220, 'price' => 69990, 'original_price' => 79990, 'stock' => 1],
        ];

        foreach ($wardrobe1Variants as $variantData) {
            $variant = Product::updateOrCreate(
                ['sku' => 'SV-VAR-WARDROBE-001-' . $variantData['length']],
                [
                    'name' => $wardrobe1->name . ' (' . $variantData['length'] . '×' . $variantData['width'] . '×' . $variantData['height'] . ' см)',
                    'slug' => Str::slug($wardrobe1->name . ' ' . $variantData['length']),
                    'price' => $variantData['price'],
                    'original_price' => $variantData['original_price'],
                    'state' => 'active',
                    'description' => $wardrobe1->description,
                    'excerpt' => $wardrobe1->excerpt,
                    'stock' => $variantData['stock'],
                    'backorder' => false,
                    'units_sold' => 0,
                    'is_variable' => false,
                    'parent_product_id' => $wardrobe1->id,
                    'color' => 'Белый глянец',
                    'color_code' => '#FFFFFF',
                    'length' => $variantData['length'],
                    'width' => $variantData['width'],
                    'height' => $variantData['height'],
                    'weight' => 80 + ($variantData['length'] - 150) / 50 * 20,
                ]
            );
            $this->syncVariantAttributes($variant, 'Белый глянец', $variantData['length'], $variantData['width'], $variantData['height']);
            $this->addRandomImage($variant, 'images');
            $totalVariants++;
        }

        $this->command->info('✅ Создано вариативных товаров: 8');
        $this->command->info('   - Всего вариаций: ' . $totalVariants);
        $this->command->info('   - Использовано изображений из локальных папок');
    }

    /**
     * Загрузить все доступные изображения из папки storage/app/public
     */
    private function loadAvailableImages(): void
    {
        $storagePath = storage_path('app/public');

        // Ищем изображения во всех подпапках, включая папку 1
        $files = File::allFiles($storagePath);

        foreach ($files as $file) {
            $extension = strtolower($file->getExtension());
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                // Пропускаем папку conversions
                if (strpos($file->getPathname(), '/conversions/') === false) {
                    $this->availableImages[] = $file->getPathname();
                }
            }
        }

        // Если изображений не найдено, используем путь, указанный пользователем
        if (empty($this->availableImages)) {
            $userPath = '/home/test/web/demo1.site.zone/public_html/storage/app/public/1';
            if (is_dir($userPath)) {
                $userFiles = File::allFiles($userPath);
                foreach ($userFiles as $file) {
                    $extension = strtolower($file->getExtension());
                    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $this->availableImages[] = $file->getPathname();
                    }
                }
            }
        }

        $this->command->info('📸 Найдено изображений: ' . count($this->availableImages));
    }

    /**
     * Получить случайное изображение
     */
    private function getRandomImage(): ?string
    {
        if (empty($this->availableImages)) {
            return null;
        }

        return $this->availableImages[array_rand($this->availableImages)];
    }

    /**
     * Добавить случайное изображение из локальных файлов
     */
    private function addRandomImage(Product $product, string $collection = 'images'): void
    {
        try {
            // Проверяем, нет ли уже изображения в этой коллекции (для главного изображения)
            if ($collection === 'images' && $product->getFirstMedia('images')) {
                return; // Главное изображение уже есть
            }

            $imagePath = $this->getRandomImage();

            if (!$imagePath || !file_exists($imagePath)) {
                $this->command->warn("   ⚠ Изображение не найдено для {$product->name}");
                return;
            }

            $product->addMedia($imagePath)
                ->toMediaCollection($collection);

            $this->command->info("   ✓ Изображение добавлено: {$product->name} ({$collection})");
        } catch (\Exception $e) {
            $this->command->warn("   ⚠ Не удалось добавить изображение для {$product->name}: {$e->getMessage()}");
        }
    }

    private function getProductCategoriesTaxonomyId(): int
    {
        $taxonomy = Taxonomy::firstOrCreate(
            ['slug' => 'product-categories'],
            [
                'name' => 'Product Categories',
                'slug' => 'product-categories',
            ]
        );

        return (int) $taxonomy->id;
    }

    /**
     * Синхронизировать product_variant_attributes для вариации: привязать цвет и/или размер по данным вариации.
     */
    private function syncVariantAttributes(Product $variant, ?string $colorName, ?int $length, ?int $width, ?int $height): void
    {
        $sync = [];

        $colorAttr = Attribute::where('slug', 'color')->where('is_use_in_variations', true)->first();
        if ($colorAttr && $colorName !== null && $colorName !== '') {
            $colorValue = AttributeValue::where('attribute_id', $colorAttr->id)
                ->where(function ($q) use ($colorName) {
                    $q->where('value', $colorName)
                        ->orWhere('slug', \Str::slug($colorName));
                })
                ->first();
            if ($colorValue) {
                $sync[$colorValue->id] = ['attribute_id' => $colorAttr->id];
            }
        }

        $sizeAttr = Attribute::where('slug', 'size')->where('is_use_in_variations', true)->first();
        if ($sizeAttr && ($length !== null || $width !== null || $height !== null)) {
            if ($length && $width && $height) {
                $sizeSlug = "{$length}x{$width}x{$height}";
            } elseif ($length && $width) {
                $sizeSlug = "{$length}x{$width}";
            } elseif ($width !== null) {
                $sizeSlug = (string) $width;
            } else {
                $sizeSlug = null;
            }
            if ($sizeSlug !== null) {
                $sizeValue = AttributeValue::where('attribute_id', $sizeAttr->id)->where('slug', $sizeSlug)->first();
                if ($sizeValue) {
                    $sync[$sizeValue->id] = ['attribute_id' => $sizeAttr->id];
                }
            }
        }

        $variant->variantAttributeValues()->sync($sync);
    }
}