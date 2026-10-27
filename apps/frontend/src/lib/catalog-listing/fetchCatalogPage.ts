import { api, type ProductListParams, type ProductListResponse } from '../api';

/** Запросы в полёте: ключ раздела и параметров → общий промис */
const inFlight = new Map<string, Promise<ProductListResponse>>();

/**
 * Страница списка товаров — один поход в API на раздел и параметры.
 *
 * Предзагрузка (`preloadCatalogPage`) и сам список стартуют почти одновременно, но живут
 * в разных кэшах SWR: у списка это `useSWRInfinite` со своим пространством ключей, поэтому
 * его fetcher не видит предзагруженный ответ. До этого выходило два одинаковых запроса
 * по 2–4 с на заход в раздел (замеры — market-docs/62).
 */
export function fetchCatalogPage(
  section: string,
  params: ProductListParams,
): Promise<ProductListResponse> {
  const key = `${section}|${JSON.stringify(params)}`;
  const running = inFlight.get(key);
  if (running) return running;

  // Ключ держим только пока запрос в полёте: ответы кэширует SWR, здесь не кэшируем
  const request = api.products.list(params).finally(() => inFlight.delete(key));
  inFlight.set(key, request);
  return request;
}
