<?php

namespace Database\Seeders;

use App\Models\Product\Category;
use App\Models\Product\ProductDeliveryBlock;
use App\Models\Product\ProductFeatureBlock;
use Illuminate\Database\Seeder;

class ProductBlocksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем блоки фич
        $featureBlocks = [
            [
                'title' => 'Доставка от 1 дня',
                'subtitle' => 'Бесплатно от 30 000 ₽',
                'icon' => 'ri-truck-line',
                'icon_color' => 'red-600',
                'bg_color' => 'red-100',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Гарантия 2 года',
                'subtitle' => 'Официальная гарантия',
                'icon' => 'ri-shield-check-line',
                'icon_color' => 'green-600',
                'bg_color' => 'green-100',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Сборка бесплатно',
                'subtitle' => 'При покупке от 30 000 ₽',
                'icon' => 'ri-tools-line',
                'icon_color' => 'yellow-600',
                'bg_color' => 'yellow-100',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'title' => 'Возврат 14 дней',
                'subtitle' => 'Без лишних вопросов',
                'icon' => 'ri-arrow-go-back-line',
                'icon_color' => 'blue-600',
                'bg_color' => 'blue-100',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        $createdFeatureBlocks = [];
        foreach ($featureBlocks as $blockData) {
            $block = ProductFeatureBlock::firstOrCreate(
                ['title' => $blockData['title']],
                $blockData
            );
            $createdFeatureBlocks[] = $block;
        }

        // Создаем блоки доставки
        $deliveryBlocks = [
            [
                'title' => 'Доставка по городу',
                'description' => 'Бесплатная доставка при заказе от 30 000 ₽. Доставка осуществляется в течение 1-3 рабочих дней после оформления заказа. Точную дату и время доставки согласовываем с вами заранее.',
                'icon' => 'ri-truck-line',
                'icon_color' => 'red-600',
                'bg_color' => 'red-100',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Доставка в регионы',
                'description' => 'Доставляем по всей России транспортными компаниями. Стоимость рассчитывается индивидуально в зависимости от региона и габаритов товара.',
                'icon' => 'ri-map-pin-line',
                'icon_color' => 'yellow-600',
                'bg_color' => 'yellow-100',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Сборка мебели',
                'description' => 'Профессиональная сборка мебели нашими специалистами - бесплатно при покупке от 30 000 ₽. Сборка производится в день доставки. Гарантия на сборку - 12 месяцев.',
                'icon' => 'ri-tools-line',
                'icon_color' => 'green-600',
                'bg_color' => 'green-100',
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        $createdDeliveryBlocks = [];
        foreach ($deliveryBlocks as $blockData) {
            $block = ProductDeliveryBlock::firstOrCreate(
                ['title' => $blockData['title']],
                $blockData
            );
            $createdDeliveryBlocks[] = $block;
        }

        // Привязываем блоки к случайным категориям
        $allCategories = Category::where('is_active', true)->get();
        
        if ($allCategories->isNotEmpty()) {
            // Выбираем случайные категории (от 30% до 70% от общего количества)
            $minCategories = max(1, (int) ceil($allCategories->count() * 0.3));
            $maxCategories = max($minCategories, (int) ceil($allCategories->count() * 0.7));
            $randomCount = rand($minCategories, $maxCategories);
            
            $randomCategories = $allCategories->random(min($randomCount, $allCategories->count()));
            
            $attachedFeatureCount = 0;
            $attachedDeliveryCount = 0;
            
            foreach ($randomCategories as $category) {
                // Привязываем блоки фич к категории (если еще не привязаны)
                foreach ($createdFeatureBlocks as $index => $block) {
                    // Проверяем существование связи через pivot таблицу напрямую
                    $pivotExists = \DB::table('category_feature_blocks')
                        ->where('category_id', $category->id)
                        ->where('feature_block_id', $block->id)
                        ->exists();
                    
                    if (!$pivotExists) {
                        $category->featureBlocks()->attach($block->id, ['sort_order' => $index + 1]);
                        $attachedFeatureCount++;
                    }
                }

                // Привязываем блоки доставки к категории (если еще не привязаны)
                foreach ($createdDeliveryBlocks as $index => $block) {
                    // Проверяем существование связи через pivot таблицу напрямую
                    $pivotExists = \DB::table('category_delivery_blocks')
                        ->where('category_id', $category->id)
                        ->where('delivery_block_id', $block->id)
                        ->exists();
                    
                    if (!$pivotExists) {
                        $category->deliveryBlocks()->attach($block->id, ['sort_order' => $index + 1]);
                        $attachedDeliveryCount++;
                    }
                }
            }

            $this->command->info("Блоки привязаны к {$randomCategories->count()} случайным категориям (из {$allCategories->count()} доступных)");
            $this->command->info("  - Создано связей фич: {$attachedFeatureCount}");
            $this->command->info("  - Создано связей доставки: {$attachedDeliveryCount}");
        } else {
            $this->command->warn('Не найдено активных категорий для привязки блоков');
        }

        $this->command->info('Создано блоков фич: ' . count($createdFeatureBlocks));
        $this->command->info('Создано блоков доставки: ' . count($createdDeliveryBlocks));
    }
}
