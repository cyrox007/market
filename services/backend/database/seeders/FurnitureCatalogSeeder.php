<?php

namespace Database\Seeders;

use App\Models\Product\Category;
use App\Models\Product\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Vanilo\Category\Models\Taxonomy;

class FurnitureCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $taxonomyId = $this->getProductCategoriesTaxonomyId();

        // --- Категории (иерархия) ---
        $sofas = $this->upsertCategory([
            'name' => 'Диваны',
            'slug' => 'divany',
            'description' => 'Диваны для гостиной, кухни и офиса.',
            'priority' => 10,
            'taxonomy_id' => $taxonomyId,
        ]);

        $sofasStraight = $this->upsertCategory([
            'name' => 'Прямые диваны',
            'slug' => 'pryamye-divany',
            'description' => 'Классические прямые диваны на каждый день.',
            'priority' => 11,
            'parent_id' => $sofas->id,
            'taxonomy_id' => $taxonomyId,
        ]);

        $sofasCorner = $this->upsertCategory([
            'name' => 'Угловые диваны',
            'slug' => 'uglovye-divany',
            'description' => 'Угловые диваны: максимум посадочных мест.',
            'priority' => 12,
            'parent_id' => $sofas->id,
            'taxonomy_id' => $taxonomyId,
        ]);

        $beds = $this->upsertCategory([
            'name' => 'Кровати',
            'slug' => 'krovati',
            'description' => 'Кровати и основания для комфортного сна.',
            'priority' => 20,
            'taxonomy_id' => $taxonomyId,
        ]);

        $bedsDouble = $this->upsertCategory([
            'name' => 'Двуспальные кровати',
            'slug' => 'dvuspalnye-krovati',
            'description' => 'Кровати 160–200 см по ширине.',
            'priority' => 21,
            'parent_id' => $beds->id,
            'taxonomy_id' => $taxonomyId,
        ]);

        $bedsSingle = $this->upsertCategory([
            'name' => 'Односпальные кровати',
            'slug' => 'odnospalnye-krovati',
            'description' => 'Кровати 80–120 см по ширине.',
            'priority' => 22,
            'parent_id' => $beds->id,
            'taxonomy_id' => $taxonomyId,
        ]);

        $mattresses = $this->upsertCategory([
            'name' => 'Матрасы',
            'slug' => 'matrasy',
            'description' => 'Ортопедические и анатомические матрасы.',
            'priority' => 30,
            'taxonomy_id' => $taxonomyId,
        ]);

        $wardrobes = $this->upsertCategory([
            'name' => 'Шкафы',
            'slug' => 'shkafy',
            'description' => 'Шкафы-купе и распашные шкафы.',
            'priority' => 40,
            'taxonomy_id' => $taxonomyId,
        ]);

        $wardrobesCoupe = $this->upsertCategory([
            'name' => 'Шкафы-купе',
            'slug' => 'shkafy-kupe',
            'description' => 'Шкафы-купе с раздвижными дверями.',
            'priority' => 41,
            'parent_id' => $wardrobes->id,
            'taxonomy_id' => $taxonomyId,
        ]);

        $wardrobesSwing = $this->upsertCategory([
            'name' => 'Распашные шкафы',
            'slug' => 'raspashnye-shkafy',
            'description' => 'Классические распашные шкафы.',
            'priority' => 42,
            'parent_id' => $wardrobes->id,
            'taxonomy_id' => $taxonomyId,
        ]);

        $tables = $this->upsertCategory([
            'name' => 'Столы',
            'slug' => 'stoly',
            'description' => 'Обеденные, письменные и журнальные столы.',
            'priority' => 50,
            'taxonomy_id' => $taxonomyId,
        ]);

        $tablesDining = $this->upsertCategory([
            'name' => 'Обеденные столы',
            'slug' => 'obedennye-stoly',
            'description' => 'Столы для кухни и столовой.',
            'priority' => 51,
            'parent_id' => $tables->id,
            'taxonomy_id' => $taxonomyId,
        ]);

        $tablesWriting = $this->upsertCategory([
            'name' => 'Письменные столы',
            'slug' => 'pismennye-stoly',
            'description' => 'Рабочие столы для дома и офиса.',
            'priority' => 52,
            'parent_id' => $tables->id,
            'taxonomy_id' => $taxonomyId,
        ]);

        $tablesCoffee = $this->upsertCategory([
            'name' => 'Журнальные столики',
            'slug' => 'zhurnalnye-stoliki',
            'description' => 'Столики для гостиной и лаунж-зоны.',
            'priority' => 53,
            'parent_id' => $tables->id,
            'taxonomy_id' => $taxonomyId,
        ]);

        $chairs = $this->upsertCategory([
            'name' => 'Стулья',
            'slug' => 'stulya',
            'description' => 'Стулья для кухни, столовой и офиса.',
            'priority' => 60,
            'taxonomy_id' => $taxonomyId,
        ]);

        $storage = $this->upsertCategory([
            'name' => 'Хранение',
            'slug' => 'hranenie',
            'description' => 'Комоды, тумбы и стеллажи.',
            'priority' => 70,
            'taxonomy_id' => $taxonomyId,
        ]);

        $dressers = $this->upsertCategory([
            'name' => 'Комоды',
            'slug' => 'komody',
            'description' => 'Комоды для спальни и гостиной.',
            'priority' => 71,
            'parent_id' => $storage->id,
            'taxonomy_id' => $taxonomyId,
        ]);

        $nightstands = $this->upsertCategory([
            'name' => 'Тумбы',
            'slug' => 'tumby',
            'description' => 'Прикроватные и ТВ-тумбы.',
            'priority' => 72,
            'parent_id' => $storage->id,
            'taxonomy_id' => $taxonomyId,
        ]);

        // --- Товары ---
        $items = [
            [
                'sku' => 'SV-SOFA-001',
                'name' => 'Диван прямой «Сканди» 2-местный',
                'price' => 24990,
                'original_price' => 29990,
                'stock' => 8,
                'excerpt' => 'Компактный прямой диван в скандинавском стиле.',
                'description' => 'Прямой диван с упругим наполнением, устойчивые деревянные ножки, обивка из износостойкой ткани. Подойдёт для небольшой гостиной или кухни-студии.',
                'categories' => [$sofasStraight, $sofas],
            ],
            [
                'sku' => 'SV-SOFA-002',
                'name' => 'Диван угловой «Модуль» с ящиком',
                'price' => 45990,
                'original_price' => null,
                'stock' => 3,
                'excerpt' => 'Угловой диван с местом для хранения и глубокими сиденьями.',
                'description' => 'Угловой диван с вместительным ящиком, универсальный угол, плотная обивка. Отличный вариант для семейной гостиной.',
                'categories' => [$sofasCorner, $sofas],
            ],
            [
                'sku' => 'SV-BED-001',
                'name' => 'Кровать двуспальная «Лофт» 160×200',
                'price' => 31990,
                'original_price' => 35990,
                'stock' => 5,
                'excerpt' => 'Металлический каркас, лаконичный дизайн.',
                'description' => 'Надёжная двуспальная кровать в стиле лофт. Усиленные ламели, устойчивое порошковое покрытие.',
                'categories' => [$bedsDouble, $beds],
            ],
            [
                'sku' => 'SV-BED-002',
                'name' => 'Кровать односпальная «Комфорт» 90×200',
                'price' => 16990,
                'original_price' => null,
                'stock' => 12,
                'excerpt' => 'Универсальная односпальная кровать для детской и гостевой.',
                'description' => 'Односпальная кровать с ортопедическим основанием. Подходит для небольших комнат, простой уход.',
                'categories' => [$bedsSingle, $beds],
            ],
            [
                'sku' => 'SV-MAT-001',
                'name' => 'Матрас ортопедический «Balance» 160×200',
                'price' => 21990,
                'original_price' => 27990,
                'stock' => 15,
                'excerpt' => 'Средняя жёсткость, независимые пружины.',
                'description' => 'Ортопедический матрас на независимом пружинном блоке, чехол из трикотажа, оптимальная поддержка позвоночника.',
                'categories' => [$mattresses],
            ],
            [
                'sku' => 'SV-WRD-001',
                'name' => 'Шкаф-купе «Практик» 180 см',
                'price' => 38990,
                'original_price' => null,
                'stock' => 4,
                'excerpt' => 'Раздвижные двери, продуманное наполнение.',
                'description' => 'Шкаф-купе с штангой и полками. Плавный ход дверей, универсальный дизайн для спальни и прихожей.',
                'categories' => [$wardrobesCoupe, $wardrobes],
            ],
            [
                'sku' => 'SV-WRD-002',
                'name' => 'Шкаф распашной «Классик» 3-дверный',
                'price' => 29990,
                'original_price' => 33990,
                'stock' => 6,
                'excerpt' => 'Распашной шкаф с полками и штангой.',
                'description' => 'Классический распашной шкаф на 3 двери. Удобные полки, крепкая фурнитура, аккуратная кромка.',
                'categories' => [$wardrobesSwing, $wardrobes],
            ],
            [
                'sku' => 'SV-TBL-001',
                'name' => 'Стол обеденный «Семейный» раздвижной',
                'price' => 18990,
                'original_price' => null,
                'stock' => 10,
                'excerpt' => 'Раздвижной механизм, 4–6 персон.',
                'description' => 'Обеденный стол с надёжным раздвижным механизмом. Подойдёт для ежедневных обедов и праздников.',
                'categories' => [$tablesDining, $tables],
            ],
            [
                'sku' => 'SV-TBL-002',
                'name' => 'Стол письменный «Офис» с тумбой',
                'price' => 14990,
                'original_price' => null,
                'stock' => 7,
                'excerpt' => 'Рабочее место с тумбой и выдвижными ящиками.',
                'description' => 'Письменный стол с тумбой для хранения. Подходит для учёбы и работы дома, аккуратный минимализм.',
                'categories' => [$tablesWriting, $tables],
            ],
            [
                'sku' => 'SV-TBL-003',
                'name' => 'Столик журнальный «Round» 60 см',
                'price' => 6990,
                'original_price' => 7990,
                'stock' => 20,
                'excerpt' => 'Круглый журнальный столик для гостиной.',
                'description' => 'Лёгкий и устойчивый журнальный столик. Идеален для кофе, книг и декора.',
                'categories' => [$tablesCoffee, $tables],
            ],
            [
                'sku' => 'SV-CHR-001',
                'name' => 'Стул кухонный «Nord» (комплект 2 шт.)',
                'price' => 7990,
                'original_price' => null,
                'stock' => 30,
                'excerpt' => 'Удобные стулья с мягким сиденьем.',
                'description' => 'Комплект из двух стульев: эргономичная спинка, мягкое сиденье, устойчивые ножки. Отлично для кухни и столовой.',
                'categories' => [$chairs],
            ],
            [
                'sku' => 'SV-STR-001',
                'name' => 'Комод «Лайн» 4 ящика',
                'price' => 13990,
                'original_price' => null,
                'stock' => 9,
                'excerpt' => 'Компактный комод для спальни и гостиной.',
                'description' => 'Комод с 4 выдвижными ящиками, плавные направляющие, лаконичный фасад. Помогает организовать хранение.',
                'categories' => [$dressers, $storage],
            ],
            [
                'sku' => 'SV-STR-002',
                'name' => 'Тумба ТВ «Медиа» 140 см',
                'price' => 12990,
                'original_price' => 14990,
                'stock' => 11,
                'excerpt' => 'ТВ-тумба с нишами под приставку и роутер.',
                'description' => 'Удобная ТВ-тумба с нишами и отделениями. Подходит для гостиной, аккуратно прячет провода и технику.',
                'categories' => [$nightstands, $storage],
            ],
        ];

        foreach ($items as $item) {
            $product = Product::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'slug' => Str::slug($item['name']),
                    'price' => $item['price'],
                    'original_price' => $item['original_price'],
                    'state' => 'active',
                    'description' => $item['description'],
                    'excerpt' => $item['excerpt'],
                    'stock' => $item['stock'],
                    'backorder' => false,
                    'units_sold' => 0,
                ]
            );

            $taxonIds = collect($item['categories'])
                ->filter()
                ->map(fn(Category $c) => $c->id)
                ->unique()
                ->values()
                ->all();

            if (!empty($taxonIds)) {
                // Привяжем категории (не трогаем возможные другие связи)
                $product->taxons()->syncWithoutDetaching($taxonIds);
            }
        }
    }

    private function upsertCategory(array $attributes): Category
    {
        $slug = $attributes['slug'] ?? Str::slug((string) ($attributes['name'] ?? ''));
        $taxonomyId = (int) ($attributes['taxonomy_id'] ?? $this->getProductCategoriesTaxonomyId());

        return Category::updateOrCreate(
            ['slug' => $slug, 'taxonomy_id' => $taxonomyId],
            [
                'name' => $attributes['name'] ?? $slug,
                'slug' => $slug,
                'taxonomy_id' => $taxonomyId,
                'description' => $attributes['description'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
                'priority' => $attributes['priority'] ?? 0,
                'parent_id' => $attributes['parent_id'] ?? null,
            ]
        );
    }

    private function getProductCategoriesTaxonomyId(): int
    {
        // taxonomy_id обязателен (и может не проставляться через model events из-за WithoutModelEvents)
        $taxonomy = Taxonomy::firstOrCreate(
            ['slug' => 'product-categories'],
            [
                'name' => 'Product Categories',
                'slug' => 'product-categories',
            ]
        );

        return (int) $taxonomy->id;
    }
}
