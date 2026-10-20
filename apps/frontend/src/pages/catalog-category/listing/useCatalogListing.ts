import { useCallback, useEffect, useMemo, useState } from 'react';
import useSWRInfinite from 'swr/infinite';
import {
  api,
  type FiltersMeta,
  type ProductListParams,
  type ProductListResponse,
} from '../../../lib/api';
import {
  filtersSignature,
  toApiParams,
  type CatalogQuery,
  type CatalogScope,
} from '../../../lib/catalog-query';
import { normalizeListProduct } from '../../../utils/cartProduct';
import { resolvePageRange, type PageRange } from './pageRange';
import {
  pageKey,
  sectionOf,
  type LoadedPage,
  type PageKey,
} from '../../../lib/catalog-listing/pageKey';
import { sameParams } from './sameParams';

export interface CatalogListingOptions {
  scope: CatalogScope;
  /** Применённый запрос — из адреса */
  query: CatalogQuery;
  /** Записать запрос в адрес */
  onQueryChange: (next: CatalogQuery) => void;
  fetchPage?: (params: ProductListParams) => Promise<ProductListResponse>;
  /** Ответ серверного рендеринга и запрос, которым он получен */
  initial?: { params: ProductListParams; response: ProductListResponse } | null;
}

export function useCatalogListing({
  scope,
  query,
  onQueryChange,
  fetchPage = api.products.list,
  initial,
}: CatalogListingOptions) {
  const base = useMemo(() => toApiParams(query, scope), [query, scope]);
  const filters = useMemo(() => JSON.stringify([scope, filtersSignature(query)]), [scope, query]);

  const section = sectionOf(scope);
  const [appending, setAppending] = useState<number | null>(null);
  const [storedRange, setStoredRange] = useState<PageRange>(() => ({
    from: query.page,
    to: query.page,
    filters,
  }));
  const range = resolvePageRange(storedRange, query.page, filters, appending);
  if (range !== storedRange) setStoredRange(range);

  const firstParams = useMemo(() => ({ ...base, page: range.from }), [base, range.from]);
  const fromServer =
    initial && range.from === range.to && sameParams(initial.params, firstParams)
      ? [initial.response]
      : undefined;

  const {
    data: loaded,
    size,
    setSize,
    isValidating,
  } = useSWRInfinite<LoadedPage, Error, (i: number) => PageKey | null>(
    (i) => (range.from + i > range.to ? null : pageKey(scope, { ...base, page: range.from + i })),
    async ([, pageSection, params]) => ({
      section: pageSection,
      response: await fetchPage(params),
    }),
    {
      fallbackData: fromServer?.map((response) => ({ section, response })),
      revalidateOnMount: !fromServer,
      revalidateFirstPage: false,
      revalidateOnFocus: false,
      keepPreviousData: true,
    },
  );

  const wantedSize = range.to - range.from + 1;
  useEffect(() => {
    if (size !== wantedSize) void setSize(wantedSize);
  }, [size, wantedSize, setSize]);

  const data =
    loaded && loaded[0]?.section === section ? loaded.map((page) => page.response) : undefined;
  const isLoadingMore = Boolean(data) && (data?.length ?? 0) < wantedSize;

  const products = useMemo(
    () =>
      (data ?? [])
        .flatMap((page) => page.data)
        .filter((p) => p.is_visible_in_region !== false)
        .map((p) => normalizeListProduct(p)),
    [data],
  );

  const first = data?.[0];
  const filtersMeta: FiltersMeta | null = first?.meta?.filters ?? null;
  const lastPage = first?.meta?.last_page ?? 1;

  const loadMore = useCallback(() => {
    const page = range.to + 1;
    setAppending(page);
    onQueryChange({ ...query, page });
  }, [range.to, query, onQueryChange]);

  const goToPage = useCallback(
    (page: number) => {
      setAppending(null);
      onQueryChange({ ...query, page });
    },
    [query, onQueryChange],
  );

  return {
    products,
    total: first?.meta?.total ?? 0,
    lastPage,
    pages: { from: range.from, to: range.to },
    hasMore: range.to < lastPage,
    isLoading: !data,
    isLoadingMore,
    /** Новый запрос грузится, на экране — прежний список */
    isRefreshing: isValidating && Boolean(data) && !isLoadingMore,
    filtersMeta,
    loadMore,
    goToPage,
  };
}
