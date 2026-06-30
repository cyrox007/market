<?php

namespace Database\Seeders;

use App\Models\Product\Attribute;
use App\Models\Product\Category;
use Illuminate\Database\Seeder;

class CategoryVariationAttributesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Добавляет атрибуты вариаций категориям для демо.
     */
    public function run(): void
    {
        // Получаем атрибуты вариаций
        $colorAttr = Attribute::where('slug', 'color')->first();
        $sizeAttr = Attribute::where('slug', 'size')->first();
        $materialAttr = Attribute::where('slug', 'material')->first();
        $komplektAttr = Attribute::where('slug', 'komplekt')->first();

        if (!$colorAttr) {
            $this->command->warn('Атрибут "color" не найден. Запустите ProductAttributesSeeder сначала.');
            return;
        }

        // Получаем все категории
        $categories = Category::all();

        if ($categories->isEmpty()) {
            $this->command->warn('Категории не найдены. Запустите FurnitureCatalogSeeder сначала.');
            return;
        }

        $this->command->info("Найдено категорий: {$categories->count()}");

        // Всем категориям добавляем цвет
        foreach ($categories as $category) {
            if (!$category->variationAttributes()->where('product_attributes.id', $colorAttr->id)->exists()) {
                $category->variationAttributes()->attach($colorAttr->id);
            }
        }
        $this->command->info("✓ Цвет добавлен всем категориям");

        // Категориям с "диван" в названии добавляем размер
        if ($sizeAttr) {
            $sofaCategories = $categories->filter(function ($cat) {
                return stripos($cat->name, 'диван') !== false;
            });
            foreach ($sofaCategories as $category) {
                if (!$category->variationAttributes()->where('product_attributes.id', $sizeAttr->id)->exists()) {
                    $category->variationAttributes()->attach($sizeAttr->id);
                }
            }
            $this->command->info("✓ Размер добавлен категориям с диванами ({$sofaCategories->count()})");
        }

        // Категориям с "угловой" добавляем материал и комплект
        if ($materialAttr && $komplektAttr) {
            $cornerCategories = $categories->filter(function ($cat) {
                return stripos($cat->name, 'угловой') !== false;
            });
            foreach ($cornerCategories as $category) {
                if (!$category->variationAttributes()->where('product_attributes.id', $materialAttr->id)->exists()) {
                    $category->variationAttributes()->attach($materialAttr->id);
                }
                if (!$category->variationAttributes()->where('product_attributes.id', $komplektAttr->id)->exists()) {
                    $category->variationAttributes()->attach($komplektAttr->id);
                }
            }
            $this->command->info("✓ Материал и Комплект добавлены угловым категориям ({$cornerCategories->count()})");
        }

        // Категориям с "шкаф" или "комод" добавляем размер
        if ($sizeAttr) {
            $furnitureCategories = $categories->filter(function ($cat) {
                $name = strtolower($cat->name);
                return stripos($name, 'шкаф') !== false || stripos($name, 'комод') !== false;
            });
            foreach ($furnitureCategories as $category) {
                if (!$category->variationAttributes()->where('product_attributes.id', $sizeAttr->id)->exists()) {
                    $category->variationAttributes()->attach($sizeAttr->id);
                }
            }
            if ($furnitureCategories->isNotEmpty()) {
                $this->command->info("✓ Размер добавлен категориям мебели ({$furnitureCategories->count()})");
            }
        }

        $this->command->info("Готово! Атрибуты вариаций добавлены категориям.");
    }
}
