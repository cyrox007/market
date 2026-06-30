<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Создаем тестового пользователя, если его еще нет
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
            ]
        );

        // Создаем менеджеров для проверки дашборда (до запуска RolesAndPermissionsSeeder)
        $managers = [
            ['email' => 'manager1@example.com', 'name' => 'Менеджер Иванов'],
            ['email' => 'manager2@example.com', 'name' => 'Менеджер Петрова'],
        ];
        foreach ($managers as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make('password'),
                ]
            );
        }

        // Заполняем типы обработки доставки
        $this->call(DeliveryHandlingTypesSeeder::class);

        // Заполняем службы доставки (carriers)
        $this->call(CarriersSeeder::class);

        // Заполняем локации доставки (новая единая модель)
        $this->call(ShippingLocationsSeeder::class);

        // Заполняем дополнительные услуги
        $this->call(AdditionalServicesSeeder::class);

        // Заполняем каталог мебельными категориями и товарами
        $this->call(FurnitureCatalogSeeder::class);

        // Атрибуты (Цвет, Размер, Комплект) с is_use_in_variations — до сидера вариаций
        $this->call(ProductAttributesSeeder::class);

        // Добавляем атрибуты вариаций категориям (для демо)
        $this->call(CategoryVariationAttributesSeeder::class);

        // Заполняем товары с вариациями и разными вариантами
        $this->call(ProductVariantsSeeder::class);

        // В каждой категории по 20 товаров (на основе существующих, с фото) — для пагинации
        $this->call(CategoryProductsSeeder::class);

        // Добавляем по 3 фото в галерею каждому товару
        $this->call(ProductGalleryPhotosSeeder::class);

        // Заполняем подборки товаров
        $this->call(ProductCollectionSeeder::class);

        // Заполняем блоки фич и доставки
        $this->call(ProductBlocksSeeder::class);

        // Заполняем магазины демо данными
        $this->call(StoresSeeder::class);

        // Заполняем идеи для интерьера с точками
        $this->call(InteriorIdeasSeeder::class);

        // Создаем роли и разрешения
        $this->call(RolesAndPermissionsSeeder::class);

        // Назначаем роли пользователям (после создания ролей)
        $testUser = User::where('email', 'test@example.com')->first();
        if ($testUser && !$testUser->hasRole('super_admin')) {
            $testUser->assignRole('super_admin');
        }
        foreach (['manager1@example.com', 'manager2@example.com'] as $email) {
            $manager = User::where('email', $email)->first();
            if ($manager && !$manager->hasRole('manager')) {
                $manager->assignRole('manager');
            }
        }

        // Создаем почтовые события
        $this->call(MailEventsSeeder::class);
    }
}
