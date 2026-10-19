import { mutate } from 'swr';
import { api } from '../api';
import { toApiParams, type CatalogQuery, type CatalogScope } from '../catalog-query';
import { pageKey, sectionOf, type LoadedPage } from './pageKey';

/** Первая страница списка — в кэш SWR заранее: список при монтировании возьмёт её оттуда */
export function preloadCatalogPage(scope: CatalogScope, query: CatalogQuery) {
  const params = toApiParams(query, scope);
  api.products
    .list(params)
    .then((response) =>
      mutate(pageKey(scope, params), { section: sectionOf(scope), response } as LoadedPage),
    )
    .catch(() => {});
}
