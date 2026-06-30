<?php

namespace Database\Seeders;

use App\Models\Page\InteriorIdea;
use App\Models\Page\InteriorIdeaHotspot;
use App\Models\Product\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class InteriorIdeasSeeder extends Seeder
{
    /**
     * Заполнение идей для интерьера с точками на изображениях
     */
    public function run(): void
    {
        // Получаем случайные товары из базы или создаем новые
        $products = $this->getOrCreateProducts();

        // Идея 1: Гостиная
        $idea1 = InteriorIdea::updateOrCreate(
            ['id' => 1],
            [
                'title' => 'Современная гостиная в скандинавском стиле',
                'is_active' => true,
                'priority' => 1,
            ]
        );

        $this->addImageToIdea($idea1, 'interior-living-room.jpg');
        
        // Точки для гостиной
        $this->createHotspot($idea1, $products[0], 30, 50, 1);
        $this->createHotspot($idea1, $products[1], 70, 40, 2);
        $this->createHotspot($idea1, $products[2], 50, 70, 3);

        $this->command->info("✓ Создана идея: {$idea1->title} с 3 точками");

        // Идея 2: Спальня
        $idea2 = InteriorIdea::updateOrCreate(
            ['id' => 2],
            [
                'title' => 'Уютная спальня в современном стиле',
                'is_active' => true,
                'priority' => 2,
            ]
        );

        $this->addImageToIdea($idea2, 'interior-bedroom.jpg');
        
        // Точки для спальни
        $this->createHotspot($idea2, $products[3], 50, 55, 1);
        $this->createHotspot($idea2, $products[4], 25, 50, 2);
        $this->createHotspot($idea2, $products[5], 75, 50, 3);

        $this->command->info("✓ Создана идея: {$idea2->title} с 3 точками");

        // Идея 3: Столовая
        $idea3 = InteriorIdea::updateOrCreate(
            ['id' => 3],
            [
                'title' => 'Элегантная столовая с обеденной зоной',
                'is_active' => true,
                'priority' => 3,
            ]
        );

        $this->addImageToIdea($idea3, 'interior-dining-room.jpg');
        
        // Точки для столовой
        $this->createHotspot($idea3, $products[6], 50, 60, 1);
        $this->createHotspot($idea3, $products[7], 35, 55, 2);
        $this->createHotspot($idea3, $products[8], 50, 30, 3);

        $this->command->info("✓ Создана идея: {$idea3->title} с 3 точками");

        $this->command->info("✓ Всего создано 3 идеи для интерьера с точками");
    }

    /**
     * Получить или создать товары для точек
     */
    private function getOrCreateProducts(): array
    {
        $products = Product::where('state', 'active')
            ->whereNull('parent_product_id')
            ->limit(9)
            ->get();

        // Если товаров недостаточно, создаем дополнительные
        if ($products->count() < 9) {
            $needed = 9 - $products->count();
            $this->command->info("   ℹ Создается {$needed} дополнительных товаров для точек");
            
            for ($i = 0; $i < $needed; $i++) {
                $newProduct = Product::factory()->create([
                    'state' => 'active',
                    'parent_product_id' => null,
                ]);
                $products->push($newProduct);
            }
        }

        $productsArray = $products->take(9)->all();
        $this->command->info("   ℹ Используется " . count($productsArray) . " товаров для точек");
        
        return $productsArray;
    }

    /**
     * Создать точку на изображении
     */
    private function createHotspot(
        InteriorIdea $idea,
        Product $product,
        float $x,
        float $y,
        int $priority
    ): void {
        InteriorIdeaHotspot::updateOrCreate(
            [
                'interior_idea_id' => $idea->id,
                'product_id' => $product->id,
            ],
            [
                'x' => $x,
                'y' => $y,
                'priority' => $priority,
            ]
        );
    }

    /**
     * Добавить изображение к идее
     */
    private function addImageToIdea(InteriorIdea $idea, string $imageName): void
    {
        // Проверяем, есть ли уже изображение
        if ($idea->getFirstMedia('image')) {
            $this->command->info("   ℹ Изображение уже существует для: {$idea->title}");
            return;
        }

        // Пытаемся найти изображение в различных местах
        $possiblePaths = [
            public_path("images/{$imageName}"),
            storage_path("app/public/images/{$imageName}"),
            database_path("seeders/images/{$imageName}"),
            resource_path("images/{$imageName}"),
        ];

        $imagePath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $imagePath = $path;
                break;
            }
        }

        // Если изображение не найдено, создаем placeholder
        if (!$imagePath) {
            $this->command->warn("   ⚠ Изображение {$imageName} не найдено, создается placeholder");
            
            // Создаем простое изображение через GD
            $imagePath = $this->createPlaceholderImage($imageName);
        }

        if ($imagePath && file_exists($imagePath)) {
            try {
                $idea->addMedia($imagePath)
                    ->toMediaCollection('image');

                $this->command->info("   ✓ Изображение добавлено: {$imageName}");
            } catch (\Exception $e) {
                $this->command->warn("   ⚠ Не удалось добавить изображение: {$e->getMessage()}");
            }
        } else {
            $this->command->warn("   ⚠ Не удалось создать или найти изображение для: {$idea->title}");
        }
    }

    /**
     * Создать placeholder изображение
     */
    private function createPlaceholderImage(string $name): ?string
    {
        // Проверяем наличие GD расширения
        if (!extension_loaded('gd')) {
            $this->command->warn("   ⚠ GD расширение не установлено, placeholder не будет создан");
            return null;
        }

        try {
            $width = 820;
            $height = 880;
            $image = imagecreatetruecolor($width, $height);
            
            if (!$image) {
                throw new \Exception('Не удалось создать изображение');
            }
            
            // Цвета
            $bgColor = imagecolorallocate($image, 240, 240, 240);
            $textColor = imagecolorallocate($image, 150, 150, 150);
            
            // Заливка фона
            imagefill($image, 0, 0, $bgColor);
            
            // Текст
            $text = "Interior Image\n" . str_replace(['interior-', '.jpg'], '', $name);
            $fontSize = 5;
            $textX = ($width - imagefontwidth($fontSize) * strlen(explode("\n", $text)[0])) / 2;
            $textY = ($height - imagefontheight($fontSize) * 2) / 2;
            
            foreach (explode("\n", $text) as $i => $line) {
                imagestring($image, $fontSize, (int)$textX, (int)($textY + ($i * 20)), $line, $textColor);
            }
            
            // Сохраняем во временную директорию
            $tempPath = sys_get_temp_dir() . '/' . uniqid('interior_', true) . '.jpg';
            imagejpeg($image, $tempPath, 90);
            imagedestroy($image);
            
            return $tempPath;
        } catch (\Exception $e) {
            $this->command->warn("   ⚠ Не удалось создать placeholder: {$e->getMessage()}");
            return null;
        }
    }
}
