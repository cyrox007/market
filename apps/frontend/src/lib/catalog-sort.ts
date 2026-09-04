/**
 * Единый модуль сортировки каталога и поиска.
 * URL: sort (поле), order (направление).
 * API: sort_by, sort_order.
 */

export const SORT_FIELDS = ['created_at', 'price', 'name'] as const;
export type SortField = (typeof SORT_FIELDS)[number];

export const SORT_ORDERS = ['asc', 'desc'] as const;
export type SortOrder = (typeof SORT_ORDERS)[number];

export const DEFAULT_SORT_FIELD: SortField = 'created_at';
export const DEFAULT_SORT_ORDER: SortOrder = 'desc';

/** 20 ломает is_variable в ответе списка товаров API — используем 18 */
export const CATALOG_PRODUCTS_PER_PAGE = 18;

export const SORT_OPTIONS: { value: string; label: string }[] = [
  { value: 'created_at-desc', label: 'Новинки' },
  { value: 'price-asc', label: 'Цена: по возрастанию' },
  { value: 'price-desc', label: 'Цена: по убыванию' },
  { value: 'name-asc', label: 'Название: А-Я' },
  { value: 'name-desc', label: 'Название: Я-А' },
];

/** Читает sort/order из URL (searchParams). */
export function getSortFromSearchParams(searchParams: URLSearchParams): {
  sortBy: SortField;
  sortOrder: SortOrder;
} {
  const sort = searchParams.get('sort') || DEFAULT_SORT_FIELD;
  const order = searchParams.get('order') || DEFAULT_SORT_ORDER;
  return {
    sortBy: SORT_FIELDS.includes(sort as SortField) ? (sort as SortField) : DEFAULT_SORT_FIELD,
    sortOrder: order === 'asc' || order === 'desc' ? order : DEFAULT_SORT_ORDER,
  };
}

/** Значение для controlled <select> (sortBy-sortOrder). */
export function getSortSelectValue(searchParams: URLSearchParams): string {
  const { sortBy, sortOrder } = getSortFromSearchParams(searchParams);
  return `${sortBy}-${sortOrder}`;
}

/** Парсит значение из <select> в безопасные sortBy и sortOrder. */
export function parseSortSelectValue(value: string): { sortBy: SortField; sortOrder: SortOrder } {
  const [field, order] = value.split('-');
  const sortBy =
    field && SORT_FIELDS.includes(field as SortField) ? (field as SortField) : DEFAULT_SORT_FIELD;
  const sortOrder = order === 'asc' || order === 'desc' ? order : DEFAULT_SORT_ORDER;
  return { sortBy, sortOrder };
}
