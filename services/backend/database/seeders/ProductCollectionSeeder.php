<?php

namespace Database\Seeders;

use App\Models\Product\ProductCollection;
use Illuminate\Database\Seeder;

class ProductCollectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем подборку "Рекомендуемые"
        $recommendedCollection = ProductCollection::firstOrCreate(
            ['slug' => 'recommended'],
            [
                'name' => 'Рекомендуемые',
                'slug' => 'recommended',
                'scope_type' => null,
                'is_auto' => false,
                'is_active' => true,
                'priority' => 10,
                'limit' => 12,
            ]
        );

        $this->command->info('Создана подборка: ' . $recommendedCollection->name . ' (slug: ' . $recommendedCollection->slug . ')');
        $this->command->info('Подборка создана как ручная. Добавьте товары через Filament админку.');
    }
}
