import type { ProductListParams, ProductListResponse } from '../../../lib/api';
import type { CatalogScope } from '../../../lib/catalog-query';

/** Раздел — в ключе отдельно от параметров: одна подкатегория в фильтре меняет category_slug */
export type PageKey = readonly ['catalog-products', string, ProductListParams];

/** Ответ с пометкой раздела: прежние данные держим на экране только в том же разделе */
export interface LoadedPage {
  section: string;
  response: ProductListResponse;
}

export const sectionOf = (scope: Pick<CatalogScope, 'scope' | 'slug'>) =>
  `${scope.scope}:${scope.slug}`;

export const pageKey = (scope: CatalogScope, params: ProductListParams): PageKey => [
  'catalog-products',
  sectionOf(scope),
  params,
];
