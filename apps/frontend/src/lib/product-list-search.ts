import type { ProductListParams } from './api';
import { SORT_TO_API } from './catalog-query/sort';
import { DEFAULT_SORT } from './catalog-query/types';

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
  'directory_external_id',
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

/**
 * Справочник адресов пока пилотный и отвечает 503 — тогда список без пункта,
 * а «по расстоянию» без него невозможно: по популярности
 */
export const retryWithoutDirectory =
  <T, P extends Pick<ProductListParams, 'directory_external_id' | 'sort_by'> = ProductListParams>(
    fetch: (params: P) => Promise<T>,
  ) =>
  async (params: P): Promise<T> => {
    try {
      return await fetch(params);
    } catch (error) {
      if (!params.directory_external_id || (error as { status?: number }).status !== 503) throw error;
      const { directory_external_id: _, ...rest } = params;
      return fetch((rest.sort_by === 'distance' ? { ...rest, ...SORT_TO_API[DEFAULT_SORT] } : rest) as P);
    }
  };
