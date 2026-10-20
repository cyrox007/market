import { useCallback } from 'react';
import { mutate } from 'swr';
import { api } from '../lib/api';
import { setCategoryFragmentFromCategory } from '../lib/category-fragment-cache';
import { parseCatalogQuery, type CatalogScope } from '../lib/catalog-query';
import { preloadCatalogPage } from '../lib/catalog-listing/preloadPage';
import { useRegion } from './useRegion';

/**
 * Предзагрузка категории и первой страницы товаров в кэш SWR при наведении на ссылку.
 * При переходе страница категории получит данные из кэша.
 */
export function usePrefetchCategory() {
  const { region } = useRegion();

  return useCallback(
    (slug: string) => {
      if (!slug) return;
      const scope: CatalogScope = { scope: 'category', slug, regionId: region?.id };

      api.categories
        .get(slug)
        .then((data) => {
          if (data?.category) setCategoryFragmentFromCategory(data.category);
          mutate(`/api/categories/${slug}`, data);
        })
        .catch(() => {});

      preloadCatalogPage(scope, parseCatalogQuery(''));
    },
    [region?.id],
  );
}
