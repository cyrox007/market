<?php

namespace Database\Seeders;

use App\Models\Page\AboutPage;
use App\Models\Page\Advantage;
use App\Models\Page\TeamMember;
use Illuminate\Database\Seeder;

class AboutPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем или получаем singleton AboutPage
        $aboutPage = AboutPage::getInstance();

        // Обновляем основные данные страницы
        $aboutPage->update([
            'hero_title' => 'О компании Светофор Мебели',
            'hero_description' => 'Более 15 лет мы создаем уют в домах наших клиентов, предлагая качественную мебель по доступным ценам',
            'story_title' => 'Наша история',
            'story_content' => "Компания \"Светофор Мебели\" была основана в 2009 году с простой, но амбициозной целью - сделать качественную мебель доступной для каждой семьи. Начав с небольшого магазина в Москве, мы постепенно расширялись, открывая новые салоны в разных городах России.\n\nСегодня \"Светофор Мебели\" - это сеть из более чем 50 магазинов в 25 городах России. Мы гордимся тем, что помогли обустроить дома более 500 000 семей, и продолжаем расти, открывая новые салоны и расширяя ассортимент.\n\nНаше название символизирует три главных принципа работы: красный - качество, желтый - доступность, зеленый - экологичность. Эти принципы остаются неизменными на протяжении всей нашей истории.",
            'statistics' => [
                'years' => 15,
                'stores' => 50,
                'clients' => 500000,
                'products' => 10000,
            ],
            'is_active' => true,
            'priority' => 0,
        ]);

        // Добавляем главное изображение для страницы, если его еще нет
        if (!$aboutPage->getFirstMedia('hero_image')) {
            try {
                $aboutPage->addMediaFromUrl('https://images.unsplash.com/photo-1524758631624-e2822e304c36?w=1920&h=1080&fit=crop')
                    ->toMediaCollection('hero_image');
                $this->command->info('   ✓ Главное изображение страницы добавлено');
            } catch (\Exception $e) {
                $this->command->warn("   ⚠ Не удалось добавить главное изображение: {$e->getMessage()}");
            }
        }

        // Создаем преимущества (ценности)
        $advantages = [
            [
                'title' => 'Качество',
                'description' => 'Мы работаем только с проверенными производителями и тщательно контролируем качество каждого изделия. Вся наша мебель имеет сертификаты соответствия и гарантию.',
                'icon' => 'ri-star-line',
                'color' => 'red',
                'priority' => 1,
            ],
            [
                'title' => 'Доступность',
                'description' => 'Мы верим, что качественная мебель должна быть доступна каждому. Поэтому предлагаем конкурентные цены, регулярные акции и удобные условия рассрочки.',
                'icon' => 'ri-price-tag-3-line',
                'color' => 'yellow',
                'priority' => 2,
            ],
            [
                'title' => 'Экологичность',
                'description' => 'Мы заботимся об окружающей среде и используем экологически чистые материалы. Наша мебель безопасна для здоровья и имеет все необходимые экологические сертификаты.',
                'icon' => 'ri-leaf-line',
                'color' => 'green',
                'priority' => 3,
            ],
        ];

        foreach ($advantages as $advantageData) {
            Advantage::updateOrCreate(
                [
                    'about_page_id' => $aboutPage->id,
                    'title' => $advantageData['title'],
                ],
                array_merge($advantageData, [
                    'about_page_id' => $aboutPage->id,
                ])
            );
        }

        // Создаем команду
        $teamMembers = [
            [
                'name' => 'Александр Петров',
                'position' => 'Генеральный директор',
                'priority' => 1,
                'image_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=400&fit=crop&crop=faces',
            ],
            [
                'name' => 'Елена Иванова',
                'position' => 'Директор по развитию',
                'priority' => 2,
                'image_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&h=400&fit=crop&crop=faces',
            ],
            [
                'name' => 'Дмитрий Сидоров',
                'position' => 'Главный дизайнер',
                'priority' => 3,
                'image_url' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=400&h=400&fit=crop&crop=faces',
            ],
            [
                'name' => 'Мария Козлова',
                'position' => 'Руководитель отдела продаж',
                'priority' => 4,
                'image_url' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=400&h=400&fit=crop&crop=faces',
            ],
        ];

        foreach ($teamMembers as $memberData) {
            $imageUrl = $memberData['image_url'];
            unset($memberData['image_url']);

            $member = TeamMember::updateOrCreate(
                [
                    'about_page_id' => $aboutPage->id,
                    'name' => $memberData['name'],
                ],
                array_merge($memberData, [
                    'about_page_id' => $aboutPage->id,
                ])
            );

            // Добавляем фото члена команды, если его еще нет
            if (!$member->getFirstMedia('image')) {
                try {
                    $member->addMediaFromUrl($imageUrl)
                        ->toMediaCollection('image');
                    $this->command->info("   ✓ Фото добавлено: {$member->name}");
                } catch (\Exception $e) {
                    $this->command->warn("   ⚠ Не удалось добавить фото для {$member->name}: {$e->getMessage()}");
                }
            }
        }

        $this->command->info('Страница "О нас" создана с ' . count($advantages) . ' преимуществами и ' . count($teamMembers) . ' членами команды.');
    }
}

