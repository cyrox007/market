import type { ReactNode } from 'react';
import { act, renderHook, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useWishlistAndCompare } from './useWishlistAndCompare';

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
  wishlist: { list: vi.fn(), toggle: vi.fn() },
  compare: { list: vi.fn(), add: vi.fn(), remove: vi.fn() },
}));
vi.mock('../lib/api', () => ({ api }));

/** Ответ сервера, который приходит, когда скажем */
function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
}

const wrapper = ({ children }: { children: ReactNode }) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
);

async function renderLoaded() {
  const hook = renderHook(() => useWishlistAndCompare(), { wrapper });
  await waitFor(() => expect(hook.result.current.isLoading).toBe(false));
  return hook;
}

beforeEach(() => {
  vi.clearAllMocks();
  api.wishlist.list.mockResolvedValue({ data: [] });
  api.compare.list.mockResolvedValue({ products: [] });
});

describe('useWishlistAndCompare', () => {
  it('marks a product as favorite before the server answers', async () => {
    const server = deferred<{ in_wishlist: boolean }>();
    api.wishlist.toggle.mockReturnValue(server.promise);
    const { result } = await renderLoaded();

    act(() => {
      void result.current.toggleWishlist(7);
    });

    await waitFor(() => expect(result.current.wishlistProductIds).toEqual([7]));
    expect(bumpWishlistCount).toHaveBeenCalledWith(1);

    await act(async () => server.resolve({ in_wishlist: true }));
    expect(result.current.wishlistProductIds).toEqual([7]);
    // Список заново не скачивается — в этом и была задержка
    expect(api.wishlist.list).toHaveBeenCalledTimes(1);
  });

  it('rolls the favorite back when the server fails', async () => {
    const server = deferred<{ in_wishlist: boolean }>();
    api.wishlist.toggle.mockReturnValue(server.promise);
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
});
