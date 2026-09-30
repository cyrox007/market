<?php

namespace Database\Seeders;

use App\Models\Page\Slider;
use Illuminate\Database\Seeder;

class SliderDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->records() as $record) {
            Slider::query()->firstOrCreate(
                ['slug' => $record['slug']],
                $record
            );
        }
    }

    /**
     * Демо-контент повторяет структуру текущей главной из ветки frontend-homepage.
     * Картинки указываются относительными URL фронта, поэтому не требуют копирования
     * бинарных файлов в backend и могут быть заменены загрузкой из админки.
     *
     * @return list<array<string,mixed>>
     */
    private function records(): array
    {
        return [
            // Верхняя большая карусель.
            [
                'placement' => Slider::PLACEMENT_TOP,
                'slot' => Slider::SLOT_MAIN,
                'slug' => 'demo-top-wholesale',
                'title' => 'Территория оптовых цен',
                'description' => 'Более 13 000 моделей от фабрик напрямую. Доставка по 16 регионам России.',
                'badge_text' => 'Сезонная распродажа до −60%',
                'badge_tone' => 'red',
                'link' => '/catalog',
                'button_text' => null,
                'image_url' => '/home/hero-1.jpg',
                'priority' => 10,
                'is_active' => true,
            ],
            [
                'placement' => Slider::PLACEMENT_TOP,
                'slot' => Slider::SLOT_MAIN,
                'slug' => 'demo-top-delivery',
                'title' => 'Доставка по 16 регионам',
                'description' => 'Собственный автопарк и склады рядом с вами.',
                'badge_text' => null,
                'badge_tone' => 'red',
                'link' => '/delivery',
                'button_text' => null,
                'image_url' => '/home/hero-2.jpg',
                'priority' => 20,
                'is_active' => true,
            ],
            [
                'placement' => Slider::PLACEMENT_TOP,
                'slot' => Slider::SLOT_MAIN,
                'slug' => 'demo-top-assembly',
                'title' => 'Сборка в день доставки',
                'description' => 'Привезём, соберём и уберём упаковку.',
                'badge_text' => null,
                'badge_tone' => 'red',
                'link' => '/delivery',
                'button_text' => null,
                'image_url' => '/home/hero-3.jpg',
                'priority' => 30,
                'is_active' => true,
            ],

            // Две карточки справа от верхней карусели. На mobile frontend их скрывает.
            [
                'placement' => Slider::PLACEMENT_TOP,
                'slot' => Slider::SLOT_SIDE,
                'slug' => 'demo-top-credit',
                'title' => 'Кредит и рассрочка',
                'description' => 'Первый взнос от 0 ₽, срок до 36 месяцев. Оформление прямо на сайте.',
                'badge_text' => 'Выгодно',
                'badge_tone' => 'yellow',
                'link' => '/delivery',
                'button_text' => 'Подробнее',
                'image_url' => '/home/promo-credit.jpg',
                'priority' => 10,
                'is_active' => true,
            ],
            [
                'placement' => Slider::PLACEMENT_TOP,
                'slot' => Slider::SLOT_SIDE,
                'slug' => 'demo-top-price-guarantee',
                'title' => 'Гарантия честной цены',
                'description' => 'Нашли дешевле у конкурента — предложим такую же цену.',
                'badge_text' => 'Гарантия',
                'badge_tone' => 'green',
                'link' => '/delivery',
                'button_text' => 'Подробнее',
                'image_url' => '/home/promo-showroom.jpg',
                'priority' => 20,
                'is_active' => true,
            ],

            // Нижний широкий слайдер.
            [
                'placement' => Slider::PLACEMENT_BOTTOM,
                'slot' => Slider::SLOT_MAIN,
                'slug' => 'demo-bottom-storage',
                'title' => 'Тут хранят порядок',
                'description' => 'Шкафы, стеллажи и комоды со скидками до 20%',
                'badge_text' => '10 — 31 августа',
                'badge_tone' => 'yellow',
                'link' => '/catalog/hranenie',
                'button_text' => 'Подробнее',
                'image_url' => '/home/banner-1.jpg',
                'priority' => 10,
                'is_active' => true,
            ],
            [
                'placement' => Slider::PLACEMENT_BOTTOM,
                'slot' => Slider::SLOT_MAIN,
                'slug' => 'demo-bottom-bedroom',
                'title' => 'Спальня, в которую хочется',
                'description' => 'Кровати и матрасы со скидками до 25%',
                'badge_text' => '1 — 30 сентября',
                'badge_tone' => 'yellow',
                'link' => '/catalog/krovati',
                'button_text' => 'Подробнее',
                'image_url' => '/home/banner-2.jpg',
                'priority' => 20,
                'is_active' => true,
            ],
            [
                'placement' => Slider::PLACEMENT_BOTTOM,
                'slot' => Slider::SLOT_MAIN,
                'slug' => 'demo-bottom-kitchen',
                'title' => 'Кухня без компромиссов',
                'description' => 'Готовые гарнитуры и столы со скидками до 20%',
                'badge_text' => 'Весь октябрь',
                'badge_tone' => 'yellow',
                'link' => '/catalog/stoly',
                'button_text' => 'Подробнее',
                'image_url' => '/home/banner-3.jpg',
                'priority' => 30,
                'is_active' => true,
            ],
            [
                'placement' => Slider::PLACEMENT_BOTTOM,
                'slot' => Slider::SLOT_MAIN,
                'slug' => 'demo-bottom-soft',
                'title' => 'Мягко сказано',
                'description' => 'Диваны и кресла со скидками до 30%',
                'badge_text' => 'До конца месяца',
                'badge_tone' => 'yellow',
                'link' => '/catalog/divany',
                'button_text' => 'Подробнее',
                'image_url' => '/home/banner-4.jpg',
                'priority' => 40,
                'is_active' => true,
            ],
            [
                'placement' => Slider::PLACEMENT_BOTTOM,
                'slot' => Slider::SLOT_MAIN,
                'slug' => 'demo-bottom-wardrobe',
                'title' => 'Всё по местам',
                'description' => 'Шкафы-купе и гардеробные со скидками до 15%',
                'badge_text' => 'Новая коллекция',
                'badge_tone' => 'yellow',
                'link' => '/catalog/shkafy',
                'button_text' => 'Подробнее',
                'image_url' => '/home/banner-5.jpg',
                'priority' => 50,
                'is_active' => true,
            ],
        ];
    }
}
