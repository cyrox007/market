import type { ReactNode } from 'react';
import { act, renderHook, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useWishlistAndCompare } from './useWishlistAndCompare';
import { resetSessionQueue } from '../lib/session-queue';
import { deferred } from '../test/deferred';

const bumpWishlistCount = vi.fn();
const bumpCompareCount = vi.fn();

vi.mock('./useCounters', () => ({
  useCounters: () => ({
    bumpWishlistCount,
    bumpCompareCount,
    refreshWishlistCount: vi.fn(),
  }),
}));

const api = vi.hoisted(() => ({
  wishlist: { list: vi.fn(), toggle: vi.fn(), add: vi.fn(), remove: vi.fn() },
  compare: { list: vi.fn(), add: vi.fn(), remove: vi.fn() },
}));
vi.mock('../lib/api', () => ({ api }));

const wrapper = ({ children }: { children: ReactNode }) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
);

async function renderLoaded() {
  const hook = renderHook(() => useWishlistAndCompare(), { wrapper });
  await waitFor(() => expect(hook.result.current.isLoading).toBe(false));
  return hook;
}

beforeEach(() => {
  vi.resetAllMocks();
  vi.restoreAllMocks();
  resetSessionQueue();
  api.wishlist.list.mockResolvedValue({ data: [] });
  api.compare.list.mockResolvedValue({ products: [] });
});

describe('useWishlistAndCompare', () => {
  it('marks a product as favorite before the server answers', async () => {
    const server = deferred<unknown>();
    api.wishlist.add.mockReturnValue(server.promise);
    const { result } = await renderLoaded();

    act(() => {
      void result.current.toggleWishlist(7);
    });

    await waitFor(() => expect(result.current.wishlistProductIds).toEqual([7]));
    expect(bumpWishlistCount).toHaveBeenCalledWith(1);

    await act(async () => server.resolve({}));
    expect(result.current.wishlistProductIds).toEqual([7]);
    // Список заново не скачивается — в этом и была задержка
    expect(api.wishlist.list).toHaveBeenCalledTimes(1);
  });

  it('rolls the favorite back when the server fails', async () => {
    const server = deferred<unknown>();
    api.wishlist.add.mockReturnValue(server.promise);
    const { result } = await renderLoaded();

    act(() => {
      void result.current.toggleWishlist(7);
    });
    await waitFor(() => expect(result.current.wishlistProductIds).toEqual([7]));

    await act(async () => server.reject(new Error('500')));

    await waitFor(() => expect(result.current.wishlistProductIds).toEqual([]));
    expect(bumpWishlistCount).toHaveBeenLastCalledWith(-1);
  });

  it('adds to compare before the server answers', async () => {
    const server = deferred<unknown>();
    api.compare.add.mockReturnValue(server.promise);
    const { result } = await renderLoaded();

    act(() => {
      void result.current.toggleCompare(3);
    });

    await waitFor(() => expect(result.current.compareProductIds).toEqual([3]));
    expect(bumpCompareCount).toHaveBeenCalledWith(1);

    await act(async () => server.resolve({}));
    expect(result.current.compareProductIds).toEqual([3]);
    expect(api.compare.list).toHaveBeenCalledTimes(1);
  });

  it('keeps both favorites when the second click lands while the first is saving', async () => {
    const first = deferred<unknown>();
    const second = deferred<unknown>();
    api.wishlist.add.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise);
    const { result } = await renderLoaded();

    act(() => {
      void result.current.toggleWishlist(7);
    });
    act(() => {
      void result.current.toggleWishlist(8);
    });
    await waitFor(() => expect(result.current.wishlistProductIds).toEqual([7, 8]));

    await act(async () => first.resolve({}));
    await act(async () => second.resolve({}));

    await waitFor(() => expect(api.wishlist.add).toHaveBeenCalledTimes(2));
    expect(result.current.wishlistProductIds).toEqual([7, 8]);
    expect(api.wishlist.toggle).not.toHaveBeenCalled();
  });

  it('treats "already in favorites" and "not in favorites" as done', async () => {
    api.wishlist.list.mockResolvedValue({ data: [{ product_id: 5 }] });
    api.wishlist.add.mockRejectedValue({ status: 422 });
    api.wishlist.remove.mockRejectedValue({ status: 404 });
    const { result } = await renderLoaded();

    await act(async () => {
      void result.current.toggleWishlist(7);
      void result.current.toggleWishlist(5);
    });

    await waitFor(() => expect(api.wishlist.remove).toHaveBeenCalledTimes(1));
    await act(async () => {});
    expect(result.current.wishlistProductIds).toEqual([7]);
  });

  it('keeps compare items the server already had and rolls back on the limit error', async () => {
    const alert = vi.spyOn(window, 'alert').mockImplementation(() => {});
    api.compare.list.mockResolvedValue({ products: [{ id: 5 }] });
    api.compare.add.mockImplementation(async (id: number) => {
      if (id === 3) throw { status: 422, data: { message: 'Товар уже в списке сравнения' } };
      throw { status: 422, data: { message: 'Максимум 5 товаров для сравнения' } };
    });
    api.compare.remove.mockRejectedValue({ status: 404 });
    const { result } = await renderLoaded();

    await act(async () => {
      void result.current.toggleCompare(3);
      void result.current.toggleCompare(4);
      void result.current.toggleCompare(5);
    });

    await waitFor(() => expect(alert).toHaveBeenCalledWith('Максимум 5 товаров для сравнения'));
    expect(alert).toHaveBeenCalledTimes(1);
    expect(result.current.compareProductIds).toEqual([3]);
  });

  it('does not add a sixth product to compare', async () => {
    const alert = vi.spyOn(window, 'alert').mockImplementation(() => {});
    api.compare.list.mockResolvedValue({ products: [1, 2, 3, 4, 5].map((id) => ({ id })) });
    const { result } = await renderLoaded();

    await act(async () => {
      void result.current.toggleCompare(6);
    });

    expect(alert).toHaveBeenCalledWith('Максимум 5 товаров для сравнения');
    expect(result.current.compareProductIds).toEqual([1, 2, 3, 4, 5]);
    expect(api.compare.add).not.toHaveBeenCalled();
    expect(bumpCompareCount).not.toHaveBeenCalled();
  });
});
