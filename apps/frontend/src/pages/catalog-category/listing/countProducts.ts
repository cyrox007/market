import { api } from '../../../lib/api';
import { toApiParams, type CatalogQuery, type CatalogScope } from '../../../lib/catalog-query';

/**
 * Число товаров для «Показать N»: список на один товар, берём total. Заменить на лёгкий
 * эндпоинт, когда бэкенд его сделает (market-docs/44, вопрос 4)
 */
export const countProductsIn =
  (scope: CatalogScope) =>
  (query: CatalogQuery): Promise<number> =>
    api.products
      .list({ ...toApiParams(query, scope), page: 1, per_page: 1 })
      .then((response) => response.meta?.total ?? 0);
