import type { ReactNode } from 'react';
import { renderHook, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ProductListParams, ProductListResponse } from '../../../lib/api';
import type { CatalogScope } from '../../../lib/catalog-query';
import { useLandingProducts } from './useLandingProducts';

const api = vi.hoisted(() => ({ products: { list: vi.fn() } }));
vi.mock('../../../lib/api', () => ({ api }));

const wrapper = ({ children }: { children: ReactNode }) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
);

const product = (id: number, visible = true) => ({
  id,
  name: `Товар ${id}`,
  slug: `t-${id}`,
  price: 1000,
  is_visible_in_region: visible,
});

const response = (...items: ReturnType<typeof product>[]) =>
  ({ data: items }) as unknown as ProductListResponse;

const kuhni: CatalogScope = { scope: 'category', slug: 'kuhni', regionId: 7 };
/** Первая страница раздела по популярности — как у списка и у сервера */
const firstPage: ProductListParams = {
  category_slug: 'kuhni',
  page: 1,
  per_page: 12,
  sort_by: 'units_sold',
  sort_order: 'desc',
  region_id: 7,
};

beforeEach(() => api.products.list.mockReset());

describe('популярные товары лендинга', () => {
  it('первые четыре с первой страницы раздела по популярности', async () => {
    api.products.list.mockResolvedValue({ data: [1, 2, 3, 4, 5].map((id) => product(id)) });
    const { result } = renderHook(() => useLandingProducts(kuhni), { wrapper });
    await waitFor(() => expect(result.current.products).toHaveLength(4));
    expect(result.current.products.map((p) => p.id)).toEqual([1, 2, 3, 4]);
    expect(api.products.list).toHaveBeenCalledWith(firstPage);
  });

  it('товары, скрытые в регионе, не показываем', async () => {
    api.products.list.mockResolvedValue({ data: [product(1), product(2, false), product(3)] });
    const { result } = renderHook(() => useLandingProducts(kuhni), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.products.map((p) => p.id)).toEqual([1, 3]);
  });

  it('пришли с сервера по тому же запросу — сразу, без запроса', () => {
    const initial = { params: firstPage, response: response(product(8), product(9)) };
    const { result } = renderHook(() => useLandingProducts(kuhni, initial), { wrapper });
    expect(result.current.products.map((p) => p.id)).toEqual([8, 9]);
    expect(result.current.isLoading).toBe(false);
    expect(api.products.list).not.toHaveBeenCalled();
  });

  it('с сервера другой запрос (другой регион) — грузит свой', async () => {
    api.products.list.mockResolvedValue({ data: [product(1)] });
    const initial = {
      params: { ...firstPage, region_id: 3 },
      response: response(product(8)),
    };
    const { result } = renderHook(() => useLandingProducts(kuhni, initial), { wrapper });
    await waitFor(() => expect(result.current.products.map((p) => p.id)).toEqual([1]));
  });

  it('нет раздела — нет запроса', () => {
    const { result } = renderHook(() => useLandingProducts(null), { wrapper });
    expect(result.current.products).toEqual([]);
    expect(api.products.list).not.toHaveBeenCalled();
  });
});
