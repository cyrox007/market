import { useCallback } from 'react';
import { mutate } from 'swr';
import { api } from '../lib/api';
import { setCategoryFragmentFromCategory } from '../lib/category-fragment-cache';
import { parseCatalogQuery, toApiParams, type CatalogScope } from '../lib/catalog-query';
import { pageKey, sectionOf, type LoadedPage } from '../pages/catalog-category/listing/pageKey';
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
      const params = toApiParams(parseCatalogQuery(''), scope);

      api.categories
        .get(slug)
        .then((data) => {
          if (data?.category) setCategoryFragmentFromCategory(data.category);
          mutate(`/api/categories/${slug}`, data);
        })
        .catch(() => {});

      api.products
        .list(params)
        .then((response) =>
          mutate(pageKey(scope, params), { section: sectionOf(scope), response } as LoadedPage),
        )
        .catch(() => {});
    },
    [region?.id],
  );
}
