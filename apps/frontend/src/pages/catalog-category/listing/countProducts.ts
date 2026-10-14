import { api } from '../../../lib/api';
import { toApiParams, type CatalogQuery, type CatalogScope } from '../../../lib/catalog-query';

/** Число товаров для «Показать N» — лёгкий GET /products/count (market-docs/44, develop 14.10) */
export const countProductsIn =
  (scope: CatalogScope) =>
  (query: CatalogQuery): Promise<number> =>
    api.products.count(toApiParams(query, scope)).then((response) => response.total);
