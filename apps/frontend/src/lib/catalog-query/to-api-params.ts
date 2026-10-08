import type { ProductListParams } from '../api';
import { SORT_TO_API } from './sort';
import { CATALOG_PER_PAGE, RANGE_KEYS, type CatalogQuery } from './types';

export interface CatalogScope {
  scope: 'category' | 'room';
  slug: string;
  regionId?: number;
}

/** Один производитель / подкатегория — параметрами, которые API уже понимает (market-docs/46) */
export function toApiParams(query: CatalogQuery, scope: CatalogScope): ProductListParams {
  const params: ProductListParams = {
    ...(scope.scope === 'room' ? { room_slug: scope.slug } : { category_slug: scope.slug }),
    page: query.page,
    per_page: CATALOG_PER_PAGE,
    ...SORT_TO_API[query.sort],
  };
  if (scope.regionId) params.region_id = scope.regionId;

  for (const key of RANGE_KEYS) {
    const { min, max } = query[key];
    if (min !== undefined) params[`${key}_min`] = min;
    if (max !== undefined) params[`${key}_max`] = max;
  }

  if (query.categories.length === 1 && scope.scope === 'category') {
    params.category_slug = query.categories[0];
  } else if (query.categories.length) {
    params.category_slugs = query.categories;
  }

  if (query.manufacturers.length === 1) params.manufacturer_slug = query.manufacturers[0];
  else if (query.manufacturers.length) params.manufacturers = query.manufacturers;

  if (query.colors.length) params.colors = query.colors;
  if (query.sizes.length) params.sizes = query.sizes;
  if (Object.keys(query.attributes).length) params.attributes = query.attributes;
  if (query.onSale) params.on_sale = true;
  return params;
}
