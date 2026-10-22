import { useMemo } from 'react';
import useSWR from 'swr';
import { api, type ProductListParams, type ProductListResponse } from '../../../lib/api';
import { parseCatalogQuery, toApiParams, type CatalogScope } from '../../../lib/catalog-query';
import { pageKey, sectionOf, type LoadedPage } from '../../../lib/catalog-listing/pageKey';
import { normalizeListProduct } from '../../../utils/cartProduct';
import { sameParams } from '../listing/sameParams';

const LANDING_PRODUCTS = 4;

/**
 * Популярные товары лендинга — первые с первой страницы раздела по популярности: тот же запрос,
 * что у сервера и предзагрузки при наведении (market-docs/55)
 */
export function useLandingProducts(
  scope: CatalogScope | null,
  initial?: { params: ProductListParams; response: ProductListResponse } | null,
  /** Сохранённый пункт ещё не прочитан — как у списка (useCatalogListing) */
  paused = false,
) {
  const params = useMemo(() => (scope ? toApiParams(parseCatalogQuery(''), scope) : null), [scope]);
  const fromServer = params && initial && sameParams(initial.params, params) ? initial : null;

  const { data, isLoading } = useSWR<LoadedPage>(
    scope && params && (!paused || fromServer) ? pageKey(scope, params) : null,
    async () => ({ section: sectionOf(scope!), response: await api.products.list(params!) }),
    {
      fallbackData: fromServer
        ? { section: sectionOf(scope!), response: fromServer.response }
        : undefined,
      revalidateOnMount: !fromServer,
      revalidateOnFocus: false,
    },
  );

  const products = useMemo(
    () =>
      (data?.response.data ?? [])
        .filter((p) => p.is_visible_in_region !== false)
        .slice(0, LANDING_PRODUCTS)
        .map((p) => normalizeListProduct(p)),
    [data],
  );

  return { products, isLoading: isLoading && !data };
}
