/** «По рейтингу» добавим, когда в API появится рейтинг (market-docs/44, вопрос 5) */
export type CatalogSort = 'popular' | 'cheap' | 'expensive';

export interface Range {
  min?: number;
  max?: number;
}

export const RANGE_KEYS = ['price', 'width', 'depth', 'height'] as const;
export type RangeKey = (typeof RANGE_KEYS)[number];

/** Ключ в адресе → поле запроса */
export const LIST_KEYS = {
  manufacturers: 'manufacturers',
  categories: 'category',
  colors: 'colors',
  sizes: 'sizes',
} as const;
export type ListKey = keyof typeof LIST_KEYS;

export interface CatalogQuery extends Record<RangeKey, Range>, Record<ListKey, string[]> {
  page: number;
  sort: CatalogSort;
  /** slug атрибута → slug значений */
  attributes: Record<string, string[]>;
  onSale: boolean;
}

/** Производитель в API — характеристика (market-docs/44, ответ 3) */
export const MANUFACTURER_ATTRIBUTE = 'proizvoditel';

export const DEFAULT_SORT: CatalogSort = 'popular';

export const CATALOG_PER_PAGE = 12;
