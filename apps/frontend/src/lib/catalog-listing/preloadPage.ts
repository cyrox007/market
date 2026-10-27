import { mutate } from 'swr';
import { toApiParams, type CatalogQuery, type CatalogScope } from '../catalog-query';
import { fetchCatalogPage } from './fetchCatalogPage';
import { pageKey, sectionOf, type LoadedPage } from './pageKey';

/** Первая страница списка — в кэш SWR заранее: список при монтировании возьмёт её оттуда */
export function preloadCatalogPage(scope: CatalogScope, query: CatalogQuery) {
  const params = toApiParams(query, scope);
  // Через общий fetchCatalogPage: список монтируется раньше, чем придёт ответ, и без этого
  // уходил второй такой же запрос
  fetchCatalogPage(sectionOf(scope), params)
    // Ответ только что с сервера — без revalidate, иначе тот, кто уже ждёт ключ, запросит его ещё раз
    .then((response) =>
      mutate(pageKey(scope, params), { section: sectionOf(scope), response } as LoadedPage, {
        revalidate: false,
      }),
    )
    .catch(() => {});
}
