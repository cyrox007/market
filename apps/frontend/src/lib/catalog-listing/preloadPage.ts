import { mutate } from 'swr';
import { api } from '../api';
import { toApiParams, type CatalogQuery, type CatalogScope } from '../catalog-query';
import { pageKey, sectionOf, type LoadedPage } from './pageKey';

/** Первая страница списка — в кэш SWR заранее: список при монтировании возьмёт её оттуда */
export function preloadCatalogPage(scope: CatalogScope, query: CatalogQuery) {
  const params = toApiParams(query, scope);
  api.products
    .list(params)
    // Ответ только что с сервера — без revalidate, иначе тот, кто уже ждёт ключ, запросит его ещё раз
    .then((response) =>
      mutate(pageKey(scope, params), { section: sectionOf(scope), response } as LoadedPage, {
        revalidate: false,
      }),
    )
    .catch(() => {});
}
