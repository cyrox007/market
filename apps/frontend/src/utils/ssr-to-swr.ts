/**
 * Утилиты для преобразования SSR данных в SWR fallback data
 */

import type { SSRContext } from '../types/ssr';

/**
 * Преобразует SSR контекст в объект fallback данных для SWR
 * Ключи соответствуют ключам SWR, которые будут использоваться в компонентах
 */
export function ssrToSwrFallback(ssrContext?: SSRContext): Record<string, any> {
  if (!ssrContext) return {};

  const fallback: Record<string, any> = {};

  // Главная страница
  if (ssrContext.home) {
    const regionId = ssrContext.region?.id;
    if (ssrContext.home.categories) {
      fallback['/api/categories'] = { data: ssrContext.home.categories };
    }
    if (ssrContext.home.featuredProducts) {
      const featuredKey = getHomeProductsKey('featured', regionId);
      fallback[featuredKey] = { data: ssrContext.home.featuredProducts };
      // Legacy fallback key without region (backward compatibility)
      fallback['/api/products/featured'] = { data: ssrContext.home.featuredProducts };
    }
    if (ssrContext.home.newProducts) {
      const newKey = getHomeProductsKey('new', regionId);
      fallback[newKey] = { data: ssrContext.home.newProducts };
      fallback['/api/products/new'] = { data: ssrContext.home.newProducts };
    }
    if (ssrContext.home.saleProducts) {
      const saleKey = getHomeProductsKey('sale', regionId);
      fallback[saleKey] = { data: ssrContext.home.saleProducts };
      fallback['/api/products/sale'] = { data: ssrContext.home.saleProducts };
    }
    if (ssrContext.home.sliders) {
      fallback['/api/sliders'] = { data: ssrContext.home.sliders };
    }
    if (ssrContext.home.interiorIdeas) {
      fallback['/api/interior-ideas'] = { data: ssrContext.home.interiorIdeas };
    }
  }

  // Страница товара
  if (ssrContext.product) {
    const productSlug = ssrContext.product.product?.slug;
    const regionId = ssrContext.region?.id;
    if (productSlug) {
      const productKey = getProductKey(productSlug, { region_id: regionId });
      fallback[productKey] = ssrContext.product;
      fallback[`/api/products/${productSlug}`] = ssrContext.product;
    }
  }

  // Страница категории
  if (ssrContext.category) {
    const categorySlug = ssrContext.category.category?.slug;
    const regionId = ssrContext.region?.id;
    if (categorySlug) {
      fallback[`/api/categories/${categorySlug}`] = { category: ssrContext.category.category };
      if (ssrContext.category.products) {
        const productsKey = getCategoryProductsKey(categorySlug, {
          page: 1,
          per_page: 18,
          sort_by: 'created_at',
          sort_order: 'desc',
          region_id: regionId,
        });
        fallback[productsKey] = ssrContext.category.products;
      }
    }
  }

  // Поиск
  if (ssrContext.search) {
    const query = ssrContext.search.query;
    if (query) {
      const regionId = ssrContext.region?.id;
      const page = ssrContext.search.products?.meta?.current_page ?? 1;
      const canonicalSearchKey = getSearchKey(query, {
        page,
        sort_by: 'created_at',
        sort_order: 'desc',
        region_id: regionId,
      });

      fallback[canonicalSearchKey] = {
        data: ssrContext.search.products?.data || [],
        meta: ssrContext.search.products?.meta,
      };
      // Legacy key для обратной совместимости.
      fallback[`/api/products/search?q=${encodeURIComponent(query)}`] = {
        data: ssrContext.search.products?.data || [],
        meta: ssrContext.search.products?.meta,
      };
    }
  }

  // Наборы
  if (ssrContext.sets) {
    fallback['/api/sets'] = ssrContext.sets;
  }

  if (ssrContext.set) {
    const setId = ssrContext.set.set?.id;
    if (setId) {
      fallback[`/api/sets/${setId}`] = ssrContext.set;
    }
  }

  return fallback;
}

/**
 * Создает ключ SWR для товара
 */
export function getProductKey(slug: string, options?: { color?: string; size?: string; region_id?: number }): string {
  const params = new URLSearchParams();
  if (options?.color) params.append('color', options.color);
  if (options?.size) params.append('size', options.size);
  if (options?.region_id) params.append('region_id', options.region_id.toString());
  const query = params.toString();
  return `/api/products/${slug}${query ? `?${query}` : ''}`;
}

export function getProductBundleKey(productId: number, regionId?: number): string {
  const params = new URLSearchParams();
  if (regionId) params.append('region_id', regionId.toString());
  const query = params.toString();
  return `/api/products/${productId}/bundle${query ? `?${query}` : ''}`;
}

export function getProductRelatedKey(productId: number, regionId?: number): string {
  const params = new URLSearchParams();
  if (regionId) params.append('region_id', regionId.toString());
  const query = params.toString();
  return `/api/products/${productId}/related${query ? `?${query}` : ''}`;
}

export interface CategoryProductsKeyOptions {
  page?: number;
  per_page?: number;
  price_min?: number;
  price_max?: number;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
  colors?: string[];
  sizes?: string[];
  attributes?: Record<string, string[]>;
  region_id?: number;
}

/**
 * Создает ключ SWR для списка товаров категории.
 * Ключ включает регион и все фильтры — кэш разный по регионам и наборам фильтров.
 * Каждая страница — отдельный ключ (малый фрагмент кэша).
 */
export function getCategoryProductsKey(
  categorySlug: string,
  options?: CategoryProductsKeyOptions
): string {
  const params = new URLSearchParams();
  params.append('category_slug', categorySlug);
  if (options?.page) params.append('page', options.page.toString());
  if (options?.per_page) params.append('per_page', options.per_page.toString());
  if (options?.price_min) params.append('price_min', options.price_min.toString());
  if (options?.price_max) params.append('price_max', options.price_max.toString());
  if (options?.sort_by) params.append('sort_by', options.sort_by);
  if (options?.sort_order) params.append('sort_order', options.sort_order);
  if (options?.colors?.length) params.append('colors', options.colors.join(','));
  if (options?.sizes?.length) params.append('sizes', options.sizes.join(','));
  if (options?.region_id) params.append('region_id', options.region_id.toString());
  if (options?.attributes) {
    Object.entries(options.attributes).forEach(([key, values]) => {
      if (values.length) params.append(`attr_${key}`, values.join(','));
    });
  }
  return `/api/products?${params.toString()}`;
}

/**
 * Парсит ключ SWR обратно в параметры для api.products.list (для fetcher в useSWRInfinite).
 */
export function parseCategoryProductsKey(
  key: string
): { category_slug: string; page: number; per_page: number; [k: string]: unknown } | null {
  const q = key.indexOf('?');
  if (q === -1) return null;
  const search = key.slice(q + 1);
  const params = new URLSearchParams(search);
  const category_slug = params.get('category_slug');
  if (!category_slug) return null;
  const attributes: Record<string, string[]> = {};
  params.forEach((value, key) => {
    if (key.startsWith('attr_')) {
      const slug = key.replace('attr_', '');
      const values = value.split(',').filter(Boolean);
      if (values.length) attributes[slug] = values;
    }
  });
  const page = Math.max(1, parseInt(params.get('page') || '1', 10));
  const per_page = Math.max(1, parseInt(params.get('per_page') || '20', 10));
  const price_min = params.get('price_min') ? parseInt(params.get('price_min')!, 10) : undefined;
  const price_max = params.get('price_max') ? parseInt(params.get('price_max')!, 10) : undefined;
  const sort_by = params.get('sort_by') || undefined;
  const sort_order = (params.get('sort_order') as 'asc' | 'desc') || undefined;
  const colors = params.get('colors') ? params.get('colors')!.split(',').filter(Boolean) : [];
  const sizes = params.get('sizes') ? params.get('sizes')!.split(',').filter(Boolean) : [];
  const region_id = params.get('region_id') ? parseInt(params.get('region_id')!, 10) : undefined;
  return {
    category_slug,
    page,
    per_page,
    ...(price_min !== undefined && { price_min }),
    ...(price_max !== undefined && { price_max }),
    ...(sort_by && { sort_by }),
    ...(sort_order && { sort_order }),
    ...(colors.length > 0 && { colors }),
    ...(sizes.length > 0 && { sizes }),
    ...(Object.keys(attributes).length > 0 && { attributes }),
    ...(region_id !== undefined && { region_id }),
  };
}

/**
 * Создает ключ SWR для поиска
 */
export function getSearchKey(
  query: string,
  options?: {
    page?: number;
    sort_by?: string;
    sort_order?: 'asc' | 'desc';
    region_id?: number;
  }
): string {
  const params = new URLSearchParams();
  params.append('q', query);
  if (options?.page) params.append('page', options.page.toString());
  if (options?.sort_by) params.append('sort_by', options.sort_by);
  if (options?.sort_order) params.append('sort_order', options.sort_order);
  if (options?.region_id) params.append('region_id', options.region_id.toString());
  return `/api/products/search?${params.toString()}`;
}

export function getHomeProductsKey(type: 'featured' | 'new' | 'sale', regionId?: number): string {
  const params = new URLSearchParams();
  if (regionId) params.append('region_id', String(regionId));
  const query = params.toString();
  return `/api/products/${type}${query ? `?${query}` : ''}`;
}
