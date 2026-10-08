import { DEFAULT_SORT, type CatalogSort } from './types';

const SORTS: readonly CatalogSort[] = ['popular', 'cheap', 'expensive'];

/** Старые ссылки: sort=<поле API>&order=asc|desc */
function readLegacySort(field: string, order: string | null): CatalogSort | null {
  if (field !== 'price') return null;
  return order === 'asc' ? 'cheap' : 'expensive';
}

export const SORT_TO_API: Record<CatalogSort, { sort_by: string; sort_order: 'asc' | 'desc' }> = {
  popular: { sort_by: 'units_sold', sort_order: 'desc' },
  cheap: { sort_by: 'price', sort_order: 'asc' },
  expensive: { sort_by: 'price', sort_order: 'desc' },
};

/** Подписи — в порядке показа в списке сортировки */
export const CATALOG_SORT_OPTIONS: { value: CatalogSort; label: string }[] = [
  { value: 'popular', label: 'По популярности' },
  { value: 'cheap', label: 'Сначала дешевле' },
  { value: 'expensive', label: 'Сначала дороже' },
];

export function readSort(params: URLSearchParams): CatalogSort {
  const value = params.get('sort');
  if (!value) return DEFAULT_SORT;
  if (SORTS.includes(value as CatalogSort)) return value as CatalogSort;
  return readLegacySort(value, params.get('order')) ?? DEFAULT_SORT;
}
