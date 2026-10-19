import type { ReactNode } from 'react';
import { renderHook, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
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

beforeEach(() => api.products.list.mockReset());

describe('популярные товары лендинга', () => {
  it('четыре самых продаваемых товара раздела в регионе', async () => {
    api.products.list.mockResolvedValue({ data: [product(1)] });
    const { result } = renderHook(() => useLandingProducts('kuhni', 7), { wrapper });
    await waitFor(() => expect(result.current.products).toHaveLength(1));
    expect(api.products.list).toHaveBeenCalledWith({
      category_slug: 'kuhni',
      page: 1,
      per_page: 4,
      sort_by: 'units_sold',
      sort_order: 'desc',
      region_id: 7,
    });
  });

  it('товары, скрытые в регионе, не показываем', async () => {
    api.products.list.mockResolvedValue({ data: [product(1), product(2, false), product(3)] });
    const { result } = renderHook(() => useLandingProducts('kuhni'), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.products.map((p) => p.id)).toEqual([1, 3]);
    expect(api.products.list.mock.calls[0][0]).not.toHaveProperty('region_id');
  });

  it('нет раздела — нет запроса', () => {
    const { result } = renderHook(() => useLandingProducts(null), { wrapper });
    expect(result.current.products).toEqual([]);
    expect(api.products.list).not.toHaveBeenCalled();
  });
});
