import { useCallback } from 'react';
import { mutate } from 'swr';
import { api } from '../lib/api';
import { setCategoryFragmentFromCategory } from '../lib/category-fragment-cache';
import { useRegion } from './useRegion';
import { getCategoryProductsKey } from '../utils/ssr-to-swr';
import { CATALOG_PRODUCTS_PER_PAGE } from '../lib/catalog-sort';

/**
 * Предзагрузка данных категории и товаров в кэш SWR при наведении на ссылку.
 * При переходе по ссылке useSWR на странице категории получит данные из кэша.
 */
export function usePrefetchCategory() {
  const { region } = useRegion();

  return useCallback(
    (slug: string) => {
      if (!slug) return;

      const categoryKey = `/api/categories/${slug}`;
      const productsParams = {
        category_slug: slug,
        page: 1,
        per_page: CATALOG_PRODUCTS_PER_PAGE,
        sort_by: 'created_at' as const,
        sort_order: 'desc' as const,
        region_id: region?.id,
      };
      const productsKey = getCategoryProductsKey(slug, {
        page: 1,
        per_page: CATALOG_PRODUCTS_PER_PAGE,
        sort_by: 'created_at',
        sort_order: 'desc',
        region_id: region?.id,
      });

      api.categories
        .get(slug)
        .then((data) => {
          if (data?.category) setCategoryFragmentFromCategory(data.category);
          mutate(categoryKey, data);
        })
        .catch(() => {});

      api.products
        .list(productsParams)
        .then((data) => mutate(productsKey, data))
        .catch(() => {});
    },
    [region?.id]
  );
}
