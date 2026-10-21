import type { ReactNode } from 'react';
import { act, renderHook, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import { describe, expect, it, vi } from 'vitest';
import type { ProductListParams, ProductListResponse } from '../../../lib/api';
import {
  parseCatalogQuery,
  toApiParams,
  type CatalogQuery,
  type CatalogScope,
} from '../../../lib/catalog-query';
import { deferred } from '../../../test/deferred';
import { useCatalogListing, type CatalogListingOptions } from './useCatalogListing';

const wrapper = ({ children }: { children: ReactNode }) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
);

const scope = { scope: 'category', slug: 'divany', regionId: 7 } as const;

/** Страница ответа как у API: пагинация в meta, товары с id `page*100 + i` */
function pageResponse(page: number, lastPage = 5): ProductListResponse {
  return {
    data: [1, 2].map((i) => ({ id: page * 100 + i, name: `Товар ${page}-${i}` }) as never),
    meta: {
      current_page: page,
      last_page: lastPage,
      per_page: 2,
      total: lastPage * 2,
      filters: { price: { min: 1000, max: 9000 }, colors: [], sizes: [], attributes: [] },
    },
  };
}

function fakeApi() {
  return vi.fn((params: ProductListParams) => Promise.resolve(pageResponse(params.page ?? 1)));
}

interface ListingProps {
  query: CatalogQuery;
  scope?: CatalogScope;
  paused?: boolean;
}

function renderListing(search: string, options: Partial<CatalogListingOptions> = {}) {
  const fetchPage = options.fetchPage ?? fakeApi();
  const onQueryChange = vi.fn<(next: CatalogQuery) => void>();
  const hook = renderHook(
    (props: ListingProps) =>
      useCatalogListing({ scope, onQueryChange, fetchPage, ...options, ...props }),
    { wrapper, initialProps: { query: parseCatalogQuery(search) } as ListingProps },
  );
  const ids = () => hook.result.current.products.map((p) => p.id);
  return { ...hook, fetchPage, onQueryChange, ids };
}

describe('useCatalogListing', () => {
  it('загружает страницу из адреса', async () => {
    const { result, fetchPage, ids } = renderListing('');
    await waitFor(() => expect(ids()).toEqual([101, 102]));
    expect(fetchPage).toHaveBeenCalledWith(
      expect.objectContaining({ category_slug: 'divany', page: 1 }),
    );
    expect(result.current).toMatchObject({ total: 10, lastPage: 5, pages: { from: 1, to: 1 } });
    expect(result.current.filtersMeta?.price).toEqual({ min: 1000, max: 9000 });
  });

  it('«Показать ещё» пишет в адрес следующую страницу и дописывает её к списку', async () => {
    const { result, onQueryChange, rerender, ids } = renderListing('');
    await waitFor(() => expect(ids()).toEqual([101, 102]));

    act(() => result.current.loadMore());
    const next = onQueryChange.mock.lastCall![0];
    expect(next.page).toBe(2);

    rerender({ query: next });
    await waitFor(() => expect(ids()).toEqual([101, 102, 201, 202]));
    expect(result.current.pages).toEqual({ from: 1, to: 2 });
  });

  it('номер страницы заменяет список', async () => {
    const { result, onQueryChange, rerender, ids } = renderListing('');
    await waitFor(() => expect(ids()).toEqual([101, 102]));

    act(() => result.current.goToPage(3));
    const next = onQueryChange.mock.lastCall![0];
    expect(next.page).toBe(3);

    rerender({ query: next });
    await waitFor(() => expect(ids()).toEqual([301, 302]));
    expect(result.current.pages).toEqual({ from: 3, to: 3 });
  });

  it('открытие адреса с ?page=2 показывает только страницу 2', async () => {
    const { result, ids } = renderListing('?page=2');
    await waitFor(() => expect(ids()).toEqual([201, 202]));
    expect(result.current.pages).toEqual({ from: 2, to: 2 });
  });

  it('на последней странице «Показать ещё» нет', async () => {
    const { result, ids } = renderListing('?page=5');
    await waitFor(() => expect(ids()).toEqual([501, 502]));
    expect(result.current.hasMore).toBe(false);
  });

  it('данные серверного рендеринга для того же запроса показываются сразу, без запроса', async () => {
    const query = parseCatalogQuery('?sort=cheap&colors=grey');
    const initial = { params: toApiParams(query, scope), response: pageResponse(1) };
    const { ids, fetchPage } = renderListing('?sort=cheap&colors=grey', { initial });
    expect(ids()).toEqual([101, 102]);
    await act(() => Promise.resolve());
    expect(fetchPage).not.toHaveBeenCalled();
  });

  it('данные серверного рендеринга для другого запроса не используются', async () => {
    const initial = {
      params: toApiParams(parseCatalogQuery(''), { ...scope, regionId: 1 }),
      response: pageResponse(9),
    };
    const { ids, fetchPage } = renderListing('', { initial });
    await waitFor(() => expect(ids()).toEqual([101, 102]));
    expect(fetchPage).toHaveBeenCalledTimes(1);
  });

  it('пока выбор пункта не прочитан — не запрашивает; потом один запрос сразу с пунктом', async () => {
    const { fetchPage, rerender, ids } = renderListing('', { paused: true });
    await act(() => Promise.resolve());
    expect(fetchPage).not.toHaveBeenCalled();

    rerender({ query: parseCatalogQuery(''), scope: { ...scope, localityId: 'eacb5f15' }, paused: false });
    await waitFor(() => expect(ids()).toEqual([101, 102]));
    expect(fetchPage).toHaveBeenCalledTimes(1);
    expect(fetchPage).toHaveBeenCalledWith(
      expect.objectContaining({ directory_external_id: 'eacb5f15' }),
    );
  });

  it('пауза не прячет данные серверного рендеринга', () => {
    const initial = { params: toApiParams(parseCatalogQuery(''), scope), response: pageResponse(1) };
    const { ids } = renderListing('', { initial, paused: true });
    expect(ids()).toEqual([101, 102]);
  });

  it('при смене фильтров прежний список и фильтры на экране, пока грузится новый', async () => {
    const slow = deferred<ProductListResponse>();
    const fetchPage = vi.fn((params: ProductListParams) =>
      params.colors ? slow.promise : Promise.resolve(pageResponse(params.page ?? 1)),
    );
    const { result, rerender, ids } = renderListing('', { fetchPage });
    await waitFor(() => expect(ids()).toEqual([101, 102]));

    rerender({ query: parseCatalogQuery('?colors=grey') });
    expect(ids()).toEqual([101, 102]);
    expect(result.current.isRefreshing).toBe(true);
    expect(result.current.filtersMeta).not.toBeNull();

    await act(async () => slow.resolve(pageResponse(4)));
    await waitFor(() => expect(ids()).toEqual([401, 402]));
    expect(result.current.isRefreshing).toBe(false);
  });

  it('пока догружается следующая страница — isLoadingMore, а не перезагрузка списка', async () => {
    const slow = deferred<ProductListResponse>();
    const fetchPage = vi.fn((params: ProductListParams) =>
      params.page === 2 ? slow.promise : Promise.resolve(pageResponse(params.page ?? 1)),
    );
    const { result, onQueryChange, rerender, ids } = renderListing('', { fetchPage });
    await waitFor(() => expect(ids()).toEqual([101, 102]));

    act(() => result.current.loadMore());
    rerender({ query: onQueryChange.mock.lastCall![0] });
    await waitFor(() => expect(result.current.isLoadingMore).toBe(true));
    expect(result.current.isRefreshing).toBe(false);

    await act(async () => slow.resolve(pageResponse(2)));
    await waitFor(() => expect(ids()).toEqual([101, 102, 201, 202]));
    expect(result.current.isLoadingMore).toBe(false);
  });

  it('в другой категории товары прежней не показываются', async () => {
    const slow = deferred<ProductListResponse>();
    const fetchPage = vi.fn((params: ProductListParams) =>
      params.category_slug === 'stulya' ? slow.promise : Promise.resolve(pageResponse(1)),
    );
    const { result, rerender, ids } = renderListing('', { fetchPage });
    await waitFor(() => expect(ids()).toEqual([101, 102]));

    rerender({ query: parseCatalogQuery(''), scope: { ...scope, slug: 'stulya' } });
    expect(ids()).toEqual([]);
    expect(result.current.isLoading).toBe(true);
    expect(result.current.filtersMeta).toBeNull();

    await act(async () => slow.resolve(pageResponse(3)));
    await waitFor(() => expect(ids()).toEqual([301, 302]));
  });

  it('выбор одной подкатегории не считается переходом в другой раздел', async () => {
    const slow = deferred<ProductListResponse>();
    const fetchPage = vi.fn((params: ProductListParams) =>
      params.category_slug === 'uglovye' ? slow.promise : Promise.resolve(pageResponse(1)),
    );
    const { rerender, ids } = renderListing('', { fetchPage });
    await waitFor(() => expect(ids()).toEqual([101, 102]));

    rerender({ query: parseCatalogQuery('?category=uglovye') });
    expect(ids()).toEqual([101, 102]);
  });
});
