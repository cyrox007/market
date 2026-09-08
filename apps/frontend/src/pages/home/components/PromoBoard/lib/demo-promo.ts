import type { PromoItem } from './promo-types';

/**
 * Заглушка на время, пока `/sliders` отдаёт ноль записей. Тексты из макета,
 * снимки стоковые. Заменится ответом API без правки компонентов.
 */
export const DEMO_SLIDES: PromoItem[] = [
  {
    id: 'wholesale',
    badge: 'Сезонная распродажа до −60%',
    title: 'Территория оптовых цен',
    description: 'Более 13 000 моделей от фабрик напрямую.\nДоставка по 16 регионам России.',
    image: '/home/hero-1.jpg',
  },
  {
    id: 'delivery',
    title: 'Доставка по 16 регионам',
    description: 'Собственный автопарк и склады рядом с вами.',
    image: '/home/hero-2.jpg',
  },
  {
    id: 'assembly',
    title: 'Сборка в день доставки',
    description: 'Привезём, соберём и уберём упаковку.',
    image: '/home/hero-3.jpg',
  },
];

export const DEMO_CARDS: PromoItem[] = [
  {
    id: 'credit',
    badge: 'Выгодно',
    badgeTone: 'yellow',
    title: 'Кредит и рассрочка',
    description: 'Первый взнос от 0 ₽, срок до 36 месяцев.\nОформление прямо на сайте.',
    image: '/home/promo-credit.jpg',
    to: '/delivery',
    linkText: 'Подробнее',
  },
  {
    id: 'guarantee',
    badge: 'Гарантия',
    badgeTone: 'green',
    title: 'Гарантия честной цены',
    description: 'Нашли дешевле у конкурента, продадим\nпо его цене.',
    image: '/home/promo-showroom.jpg',
    to: '/delivery',
    linkText: 'Подробнее',
  },
];

/** Пока у категорий в API поля картинок пустые — подставляем по слагу */
export const DEMO_CATEGORY_IMAGES: Record<string, string> = {
  divany: '/home/cat-divany.jpg',
  krovati: '/home/cat-krovati.jpg',
  matrasy: '/home/cat-matrasy.jpg',
  shkafy: '/home/cat-shkafy.jpg',
  stoly: '/home/cat-stoly.jpg',
  stulya: '/home/cat-stulya.jpg',
  hranenie: '/home/cat-hranenie.jpg',
};

/**
 * Добор карусели до девяти карточек: в базе пока семь корневых категорий,
 * а в макете одиннадцать. Разделов под эти два в API нет — уйдут сами,
 * как только бэкендер заведёт настоящие.
 */
export const DEMO_EXTRA_CATEGORIES = [
  { id: 'demo-kuhni', name: 'Кухни', slug: 'kuhni', image: '/home/cat-kuhni.jpg' },
  { id: 'demo-prihozhie', name: 'Прихожие', slug: 'prihozhie', image: '/home/cat-prihozhie.jpg' },
];
