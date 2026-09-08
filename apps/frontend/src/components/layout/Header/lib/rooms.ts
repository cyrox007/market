import type { MenuSection } from './menu-types';

/**
 * Комнаты из макета. Все пункты неактивны: источника данных для них в API нет
 * вовсе — ни дерева комнат, ни группировки категорий. Разбор и варианты для
 * бэкендера — market-docs/14-header-wiring.md.
 */
const LIVING_ROOM_GROUPS: MenuSection['groups'] = [
  {
    title: 'Стенки и хранение',
    links: ['Готовые стенки', 'Модульные стенки', 'Витрины', 'Библиотеки', 'Тумбы ТВ'].map(
      (label) => ({ to: '#', label, disabled: true }),
    ),
  },
  {
    title: 'Диваны и кресла',
    links: ['Диваны прямые', 'Диваны угловые', 'Кресла', 'Пуфики, банкетки', 'Комплекты'].map(
      (label) => ({ to: '#', label, disabled: true }),
    ),
  },
  {
    title: 'Стеллажи и полки',
    links: ['Стеллажи', 'Навесные полки', 'Угловые завершения'].map((label) => ({
      to: '#',
      label,
      disabled: true,
    })),
  },
  {
    title: 'Столы',
    links: ['Журнальные столы', 'Компьютерные столы'].map((label) => ({
      to: '#',
      label,
      disabled: true,
    })),
  },
];

export const ROOMS_SECTIONS: MenuSection[] = [
  { id: 'gostinaya', label: 'Гостиная', groups: LIVING_ROOM_GROUPS },
  { id: 'spalnya', label: 'Спальня', disabled: true },
  { id: 'kuhnya', label: 'Кухня', disabled: true },
  { id: 'detskaya', label: 'Детская', disabled: true },
  { id: 'prihozhaya', label: 'Прихожая', disabled: true },
  { id: 'kabinet', label: 'Кабинет', disabled: true },
  { id: 'banya', label: 'Баня и сауна', disabled: true },
  { id: 'dacha', label: 'Дача и отдых', disabled: true },
];
