import type { PromoItem } from '../../PromoBoard/lib/promo-types';

/**
 * Заглушка на время, пока `/sliders` отдаёт ноль записей и сидера под них в проекте нет.
 * Поля те же, что у верхнего промо-блока, — заменится ответом API без правки компонентов.
 */
export const DEMO_BANNERS: PromoItem[] = [
  {
    id: 'storage',
    badge: '10 — 31 августа',
    title: 'Тут хранят порядок',
    description: 'Шкафы, стеллажи и комоды со скидками до 20%',
    image: '/home/banner-1.jpg',
    to: '/catalog/hranenie',
    linkText: 'Подробнее',
  },
  {
    id: 'sleep',
    badge: '1 — 30 сентября',
    title: 'Спальня, в которую хочется',
    description: 'Кровати и матрасы со скидками до 25%',
    image: '/home/banner-2.jpg',
    to: '/catalog/krovati',
    linkText: 'Подробнее',
  },
  {
    id: 'kitchen',
    badge: 'Весь октябрь',
    title: 'Кухня без компромиссов',
    description: 'Готовые гарнитуры и столы со скидками до 20%',
    image: '/home/banner-3.jpg',
    to: '/catalog/stoly',
    linkText: 'Подробнее',
  },
  {
    id: 'soft',
    badge: 'До конца месяца',
    title: 'Мягко сказано',
    description: 'Диваны и кресла со скидками до 30%',
    image: '/home/banner-4.jpg',
    to: '/catalog/divany',
    linkText: 'Подробнее',
  },
  {
    id: 'wardrobe',
    badge: 'Новая коллекция',
    title: 'Всё по местам',
    description: 'Шкафы-купе и гардеробные со скидками до 15%',
    image: '/home/banner-5.jpg',
    to: '/catalog/shkafy',
    linkText: 'Подробнее',
  },
];
