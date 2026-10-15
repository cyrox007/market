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

  // Шапка: дерево категорий есть на любом маршруте
  if (ssrContext.categoryTree?.length) {
    fallback['/api/categories/tree'] = { tree: ssrContext.categoryTree };
  }

  // Шапка: дерево комнат — вторая таксономия, тоже на любом маршруте
  if (ssrContext.roomTree?.length) {
    fallback['/api/rooms/tree'] = { tree: ssrContext.roomTree };
  }

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
    if (categorySlug) {
      fallback[`/api/categories/${categorySlug}`] = { category: ssrContext.category.category };
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
export function getProductKey(
  slug: string,
  options?: { color?: string; size?: string; region_id?: number },
): string {
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
  },
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
