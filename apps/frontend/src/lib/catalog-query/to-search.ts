import { writeAttributes, writeLists } from './lists';
import { writeRanges } from './ranges';
import { DEFAULT_SORT, type CatalogQuery } from './types';

/** Порядок параметров постоянный — один и тот же выбор даёт один адрес */
export function toCatalogSearch(query: CatalogQuery): string {
  const params = new URLSearchParams();
  if (query.sort !== DEFAULT_SORT) params.set('sort', query.sort);
  writeRanges(params, query);
  writeLists(params, query);
  writeAttributes(params, query.attributes);
  if (query.onSale) params.set('on_sale', '1');
  if (query.page > 1) params.set('page', String(query.page));
  return params.toString();
}

/** Выбор без страницы: одинаковые фильтры и сортировка — одна подпись */
export const filtersSignature = (query: CatalogQuery) => toCatalogSearch({ ...query, page: 1 });
