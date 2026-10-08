import { readAttributes, readLists } from './lists';
import { readRanges } from './ranges';
import { readSort } from './sort';
import type { CatalogQuery } from './types';

function readPage(params: URLSearchParams): number {
  const page = Number(params.get('page'));
  return Number.isInteger(page) && page > 1 ? page : 1;
}

export function parseCatalogQuery(search: string | URLSearchParams): CatalogQuery {
  const params = new URLSearchParams(search);
  return {
    page: readPage(params),
    sort: readSort(params),
    ...readRanges(params),
    ...readLists(params),
    attributes: readAttributes(params),
    onSale: params.get('on_sale') === '1',
  };
}
