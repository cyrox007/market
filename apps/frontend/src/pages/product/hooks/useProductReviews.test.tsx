import { renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useProductReviews } from './useProductReviews';

const list = vi.fn();
let ssr: unknown = null;

vi.mock('../../../lib/api', () => ({ api: { reviews: { list: (id: number) => list(id) } } }));
vi.mock('../../../contexts/ssr-context', () => ({ useSSR: () => ssr }));

const wrapper = ({ children }: { children: ReactNode }) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
);
const r = { id: 1, name: 'Анна', rating: 5, comment: 'Хорошо', created_at: '1 May 2026' };

describe('useProductReviews', () => {
  beforeEach(() => {
    ssr = null;
    list.mockReset().mockResolvedValue({ data: [r] });
  });

  it('отзывов нет — не запрашивает', () => {
    const { result } = renderHook(() => useProductReviews(7, 0), { wrapper });
    expect(list).not.toHaveBeenCalled();
    expect(result.current).toMatchObject({ reviews: [], status: 'ready' });
  });

  it('грузит список товара', async () => {
    const { result } = renderHook(() => useProductReviews(7, 1), { wrapper });
    expect(result.current.status).toBe('loading');
    await waitFor(() => expect(result.current.status).toBe('ready'));
    expect(list).toHaveBeenCalledWith(7);
    expect(result.current.reviews).toEqual([r]);
  });

  it('отзывы с сервера (SSR) этого товара — сразу, без запроса', () => {
    ssr = { productReviews: { productId: 7, data: [r] } };
    const { result } = renderHook(() => useProductReviews(7, 1), { wrapper });
    expect(result.current).toMatchObject({ reviews: [r], status: 'ready' });
    expect(list).not.toHaveBeenCalled();
  });

  it('SSR другого товара не подставляет', async () => {
    ssr = { productReviews: { productId: 8, data: [] } };
    const { result } = renderHook(() => useProductReviews(7, 1), { wrapper });
    await waitFor(() => expect(result.current.reviews).toEqual([r]));
  });
});
