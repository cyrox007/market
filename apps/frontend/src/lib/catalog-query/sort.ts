import { DEFAULT_SORT, type CatalogSort } from './types';

/** Старые ссылки: sort=<поле API>&order=asc|desc */
function readLegacySort(field: string, order: string | null): CatalogSort | null {
  if (field !== 'price') return null;
  return order === 'asc' ? 'cheap' : 'expensive';
}

export const SORT_TO_API: Record<CatalogSort, { sort_by: string; sort_order: 'asc' | 'desc' }> = {
  popular: { sort_by: 'units_sold', sort_order: 'desc' },
  rating: { sort_by: 'rating', sort_order: 'desc' },
  cheap: { sort_by: 'price', sort_order: 'asc' },
  expensive: { sort_by: 'price', sort_order: 'desc' },
  nearest: { sort_by: 'distance', sort_order: 'asc' },
};

const SORTS = Object.keys(SORT_TO_API) as CatalogSort[];

/** Подписи — в порядке показа в списке сортировки */
export const CATALOG_SORT_OPTIONS: { value: CatalogSort; label: string }[] = [
  { value: 'popular', label: 'По популярности' },
  { value: 'rating', label: 'По рейтингу' },
  { value: 'cheap', label: 'Сначала дешевле' },
  { value: 'expensive', label: 'Сначала дороже' },
];

/** «Сначала ближе» — только когда выбран пункт: без него бэк по расстоянию не сортирует */
export const catalogSortOptions = (nearby: boolean) =>
  nearby
    ? [...CATALOG_SORT_OPTIONS, { value: 'nearest' as const, label: 'Сначала ближе' }]
    : CATALOG_SORT_OPTIONS;

export function readSort(params: URLSearchParams): CatalogSort {
  const value = params.get('sort');
  if (!value) return DEFAULT_SORT;
  if (SORTS.includes(value as CatalogSort)) return value as CatalogSort;
  return readLegacySort(value, params.get('order')) ?? DEFAULT_SORT;
}

/** Поиск сортирует так же, как каталог: адрес sort=cheap, в API — sort_by / sort_order */
export function searchSort(params: URLSearchParams) {
  // Поиск по расстоянию не сортирует (market-docs/15)
  const read = readSort(params);
  const sort = read === 'nearest' ? DEFAULT_SORT : read;
  return { sort, ...SORT_TO_API[sort] };
}

/** Адрес поиска с новой сортировкой — с первой страницы */
export function withSort(params: URLSearchParams, sort: CatalogSort): URLSearchParams {
  const next = new URLSearchParams(params);
  next.delete('order');
  next.delete('page');
  if (sort === DEFAULT_SORT) next.delete('sort');
  else next.set('sort', sort);
  return next;
}
