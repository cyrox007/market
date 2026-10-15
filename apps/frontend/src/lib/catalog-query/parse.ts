import { readAttributes, readLists } from './lists';
import { readRanges } from './ranges';
import { readSort } from './sort';
import { MANUFACTURER_ATTRIBUTE, type CatalogQuery } from './types';

function readPage(params: URLSearchParams): number {
  const page = Number(params.get('page'));
  return Number.isInteger(page) && page > 1 ? page : 1;
}

export function parseCatalogQuery(search: string | URLSearchParams): CatalogQuery {
  const params = new URLSearchParams(search);
  const lists = readLists(params);
  // attr_proizvoditel — формат API; в запросе это производители
  const { [MANUFACTURER_ATTRIBUTE]: makers = [], ...attributes } = readAttributes(params);
  return {
    page: readPage(params),
    sort: readSort(params),
    ...readRanges(params),
    ...lists,
    manufacturers: [...new Set([...lists.manufacturers, ...makers])],
    attributes,
    onSale: params.get('on_sale') === '1',
  };
}
