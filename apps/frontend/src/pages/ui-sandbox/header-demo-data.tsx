import { MenuPromo } from '../../components/layout/Header';
import type { MenuSection } from '../../components/layout/Header';

/**
 * Демонстрационные данные меню шапки, списаны с макета.
 *
 * Живут рядом с песочницей, а не в компоненте: в бою разделы приходят из API
 * категорий, шапка их только отрисовывает. Файл уезжает из прод-сборки вместе
 * с песочницей — он больше ниоткуда не импортируется.
 */

const KITCHEN_LINKS = [
  'Готовые кухни',
  'Модульные кухни',
  'Кухонные углы',
  'Кухонные столы',
  'Обеденные группы',
  'Стулья',
  'Табуреты',
  'Кухонные диваны',
  'Кухонные угловые диваны',
  'Буфеты и серванты',
  'Мойки',
];

const slug = (label: string) => `/catalog/${encodeURIComponent(label.toLowerCase())}`;

const plain = (labels: string[]): MenuSection['groups'] => [
  { links: labels.map((label) => ({ to: slug(label), label })) },
];

export const CATALOG_SECTIONS: MenuSection[] = [
  { id: 'stock', label: 'Сток', to: '/catalog/stock', emphasized: true },
  { id: 'sale', label: 'Акции', to: '/catalog/sale', emphasized: true },
  { id: 'gostinye', label: 'Гостиные', groups: plain(['Стенки', 'Тумбы ТВ', 'Витрины']) },
  {
    id: 'detskaya',
    label: 'Детская мебель',
    groups: plain(['Кровати', 'Столы', 'Шкафы', 'Комплекты']),
  },
  { id: 'kuhni', label: 'Кухни', groups: plain(KITCHEN_LINKS) },
  { id: 'banya', label: 'Мебель для бани', groups: plain(['Полки', 'Скамьи']) },
  { id: 'myagkaya', label: 'Мягкая мебель', groups: plain(['Диваны', 'Кресла', 'Пуфы']) },
  { id: 'prihozhie', label: 'Прихожие', groups: plain(['Шкафы', 'Вешалки', 'Обувницы']) },
  { id: 'spalni', label: 'Спальни', groups: plain(['Кровати', 'Комоды', 'Тумбы']) },
  { id: 'stellazhi', label: 'Стеллажи и полки', groups: plain(['Стеллажи', 'Навесные полки']) },
  { id: 'stoly', label: 'Столы', groups: plain(['Обеденные', 'Журнальные', 'Компьютерные']) },
  { id: 'dom', label: 'Товары для дома', groups: plain(['Зеркала', 'Ковры']) },
  { id: 'otdyh', label: 'Товары для отдыха', groups: plain(['Кресла-мешки', 'Гамаки']) },
  { id: 'shkafy', label: 'Шкафы', groups: plain(['Распашные', 'Купе', 'Угловые']) },
  { id: 'matrasy', label: 'Матрасы', groups: plain(['Пружинные', 'Беспружинные']) },
  { id: 'namatrasniki', label: 'Наматрасники', groups: plain(['Защитные', 'Топперы']) },
  { id: 'ofis', label: 'Офисная мебель', groups: plain(['Столы', 'Кресла', 'Шкафы']) },
];

const LIVING_ROOM_PROMO = (
  <MenuPromo
    badge="Распродажа до −60%"
    title="Гостиная «Элирия»"
    price={12030}
    oldPrice={29870}
    to="/catalog/sale"
    suggestionsTitle="Так же подходит для гостиной"
    suggestions={[
      {
        to: '/product/polka-365-04',
        name: 'Полка навесная 365.04 «Честерфилд»',
        price: 10350,
        oldPrice: 17600,
      },
      { to: '/product/shkaf-413-01', name: 'Шкаф 413.01 «Норден»', price: 18340, oldPrice: 31180 },
      { to: '/product/polka-321-05', name: 'Полка 321.05 «Норден»', price: 2420, oldPrice: 4120 },
    ]}
  />
);

export const ROOMS_SECTIONS: MenuSection[] = [
  {
    id: 'gostinaya',
    label: 'Гостиная',
    promo: LIVING_ROOM_PROMO,
    groups: [
      {
        title: 'Стенки и хранение',
        links: ['Готовые стенки', 'Модульные стенки', 'Витрины', 'Библиотеки', 'Тумбы ТВ'].map(
          (label) => ({ to: slug(label), label }),
        ),
      },
      {
        title: 'Диваны и кресла',
        links: ['Диваны прямые', 'Диваны угловые', 'Кресла', 'Пуфики, банкетки', 'Комплекты'].map(
          (label) => ({ to: slug(label), label }),
        ),
      },
      {
        title: 'Стеллажи и полки',
        links: ['Стеллажи', 'Навесные полки', 'Угловые завершения'].map((label) => ({
          to: slug(label),
          label,
        })),
      },
      {
        title: 'Столы',
        links: ['Журнальные столы', 'Компьютерные столы'].map((label) => ({
          to: slug(label),
          label,
        })),
      },
    ],
  },
  {
    id: 'spalnya',
    label: 'Спальня',
    groups: [
      {
        title: 'Кровати',
        links: ['Односпальные', 'Двуспальные', 'С подъёмным механизмом'].map((label) => ({
          to: slug(label),
          label,
        })),
      },
      {
        title: 'Хранение',
        links: ['Комоды', 'Тумбы', 'Шкафы'].map((label) => ({ to: slug(label), label })),
      },
    ],
  },
  { id: 'kuhnya', label: 'Кухня', groups: plain(KITCHEN_LINKS) },
  { id: 'detskaya-room', label: 'Детская', groups: plain(['Кровати', 'Столы', 'Стеллажи']) },
  { id: 'prihozhaya', label: 'Прихожая', groups: plain(['Шкафы', 'Вешалки', 'Обувницы']) },
  { id: 'kabinet', label: 'Кабинет', groups: plain(['Столы', 'Кресла', 'Стеллажи']) },
  { id: 'banya-room', label: 'Баня и сауна', groups: plain(['Полки', 'Скамьи', 'Аксессуары']) },
  { id: 'dacha', label: 'Дача и отдых', groups: plain(['Садовая мебель', 'Гамаки']) },
];
