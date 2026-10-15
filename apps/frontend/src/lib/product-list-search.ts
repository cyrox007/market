import type { ProductListParams } from './api';

const SCALARS = [
  'category_id',
  'category_slug',
  'room_slug',
  'price_min',
  'price_max',
  'search',
  'sort_by',
  'sort_order',
  'per_page',
  'page',
] as const;

const LISTS = ['colors', 'sizes'] as const;

const AFTER_ATTRIBUTES = [
  'manufacturer_slug',
  'manufacturers',
  'category_slugs',
  'on_sale',
  'width_min',
  'width_max',
  'depth_min',
  'depth_max',
  'height_min',
  'height_max',
  'region_id',
] as const;

function append(query: URLSearchParams, name: string, value: unknown) {
  if (value === undefined || value === null || value === '' || value === false) return;
  if (Array.isArray(value)) {
    if (value.length) query.append(name, value.join(','));
    return;
  }
  query.append(name, value === true ? '1' : String(value));
}

/** Строка запроса GET /products — общая для браузера и серверного рендеринга */
export function toProductListSearch(params: ProductListParams = {}): string {
  const query = new URLSearchParams();
  for (const name of SCALARS) append(query, name, params[name]);
  for (const name of LISTS) append(query, name, params[name]);
  for (const [slug, values] of Object.entries(params.attributes ?? {})) {
    for (const value of values) query.append(`attributes[${slug}][]`, value);
  }
  for (const name of AFTER_ATTRIBUTES) append(query, name, params[name]);
  return query.toString();
}
