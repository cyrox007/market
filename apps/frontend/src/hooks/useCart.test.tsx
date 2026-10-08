import type { ReactNode } from 'react';
import { act, renderHook, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useCart } from './useCart';
import { resetSessionQueue } from '../lib/session-queue';
import { deferred, flush } from '../test/deferred';
import type { Cart, CartItem } from '../lib/api';

const showCartToast = vi.fn();

vi.mock('./useCounters', () => ({ useCounters: () => ({ setCartCount: vi.fn() }) }));
vi.mock('./useRegion', () => ({ useRegion: () => ({ getRegionId: () => null }) }));
vi.mock('../contexts/cart-toast-context', () => ({ useCartToast: () => ({ showCartToast }) }));

const api = vi.hoisted(() => ({
  cart: { get: vi.fn(), add: vi.fn(), update: vi.fn(), remove: vi.fn() },
}));
vi.mock('../lib/api', () => ({ api }));

function line(id: number, productId: number, quantity: number): CartItem {
  return { id, product_id: productId, name: 'Стол', slug: 'stol', price: 100, quantity, total: 100 * quantity, image: null, sku: null } as CartItem;
}

function cartOf(...items: CartItem[]): Cart {
  const item_count = items.reduce((s, i) => s + i.quantity, 0);
  return { items, subtotal: item_count * 100, item_count, is_empty: items.length === 0 } as Cart;
}

const wrapper = ({ children }: { children: ReactNode }) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
);

/** `after` — что отдаст сервер на сверке после изменений */
async function renderCart(initial: Cart, after: Cart = initial) {
  api.cart.get.mockResolvedValueOnce(initial).mockResolvedValue(after);
  const hook = renderHook(() => useCart(), { wrapper });
  await waitFor(() => expect(hook.result.current.cart).not.toBeNull());
  return hook;
}

const quantityOf = (cart: Cart | null, productId: number) =>
  cart?.items.find((i) => i.product_id === productId)?.quantity ?? 0;

beforeEach(() => {
  vi.resetAllMocks();
  resetSessionQueue();
});

describe('useCart.setCartQuantity', () => {
  it('shows a new line at once and takes its id from the server', async () => {
    const server = deferred<unknown>();
    api.cart.add.mockReturnValue(server.promise);
    const { result } = await renderCart(cartOf(), cartOf(line(55, 7, 1)));

    act(() => {
      result.current.setCartQuantity(7, 1, { preview: { name: 'Стол', price: 100 } });
    });

    expect(quantityOf(result.current.cart, 7)).toBe(1);
    await waitFor(() =>
      expect(api.cart.add).toHaveBeenCalledWith(expect.objectContaining({ product_id: 7, quantity: 1 })),
    );

    await act(async () => server.resolve({ item: line(55, 7, 1), was_adjusted: false, message: '' }));
    expect(result.current.cart?.items[0].id).toBe(55);
  });

  it('turns five quick clicks into two requests, the second to the new line id', async () => {
    const first = deferred<unknown>();
    api.cart.update
      .mockReturnValueOnce(first.promise)
      .mockResolvedValueOnce({ item: line(12, 7, 6), was_adjusted: false, message: '' });
    const { result } = await renderCart(cartOf(line(10, 7, 1)), cartOf(line(12, 7, 6)));

    await act(async () => {
      result.current.setCartQuantity(7, 2);
      await flush();
    });
    for (let qty = 3; qty <= 6; qty += 1) {
      act(() => {
        result.current.setCartQuantity(7, qty);
      });
    }
    expect(quantityOf(result.current.cart, 7)).toBe(6);

    await act(async () => first.resolve({ item: line(11, 7, 2), was_adjusted: false, message: '' }));

    await waitFor(() => expect(api.cart.update).toHaveBeenCalledTimes(2));
    expect(api.cart.update.mock.calls.map(([id, body]) => [id, body.quantity])).toEqual([
      [10, 2],
      [11, 6],
    ]);
    expect(api.cart.add).not.toHaveBeenCalled();
    await waitFor(() => expect(result.current.cart?.items[0].id).toBe(12));
    expect(quantityOf(result.current.cart, 7)).toBe(6);
  });

  it('removes the line at zero, a line already gone counts as removed', async () => {
    api.cart.remove.mockRejectedValueOnce({ status: 404 }).mockResolvedValueOnce({});
    const { result } = await renderCart(cartOf(line(10, 7, 2), line(20, 8, 1)), cartOf());

    act(() => {
      result.current.setCartQuantity(7, 0);
      result.current.setCartQuantity(8, 0);
    });
    expect(result.current.cart?.items).toEqual([]);

    await waitFor(() => expect(api.cart.remove).toHaveBeenCalledTimes(2));
    expect(api.cart.remove.mock.calls).toEqual([[10], [20]]);
    await act(async () => {});
    expect(result.current.cart?.items).toEqual([]);
    expect(showCartToast).not.toHaveBeenCalled();
  });

  it('shows the quantity the server allowed and tells why', async () => {
    const message = 'Товар обновлен (запрошено 8, доступно только 5 шт. на складе)';
    api.cart.update.mockResolvedValue({ item: line(11, 7, 5), was_adjusted: true, message });
    const { result } = await renderCart(cartOf(line(10, 7, 1)), cartOf(line(11, 7, 5)));

    act(() => {
      result.current.setCartQuantity(7, 8);
    });

    await waitFor(() => expect(quantityOf(result.current.cart, 7)).toBe(5));
    expect(showCartToast).toHaveBeenCalledWith(message, 'success');
    expect(api.cart.update).toHaveBeenCalledTimes(1);
  });

  it('rolls back only the failed product and shows the error', async () => {
    api.cart.update.mockImplementation(async (id: number) => {
      if (id === 10) throw { status: 422, data: { message: 'Товар недоступен для заказа' } };
      return { item: line(21, 8, 3), was_adjusted: false, message: '' };
    });
    const { result } = await renderCart(cartOf(line(10, 7, 1), line(20, 8, 1)), cartOf(line(10, 7, 1), line(21, 8, 3)));

    act(() => {
      result.current.setCartQuantity(7, 2);
      result.current.setCartQuantity(8, 3);
    });

    await waitFor(() => expect(showCartToast).toHaveBeenCalledWith('Товар недоступен для заказа', 'error'));
    expect(quantityOf(result.current.cart, 7)).toBe(1);
    expect(quantityOf(result.current.cart, 8)).toBe(3);
  });

  it('never goes above the stock', async () => {
    api.cart.update.mockImplementation(async (_id: number, body: { quantity: number }) => ({
      item: line(11, 7, body.quantity),
      was_adjusted: false,
      message: '',
    }));
    const { result } = await renderCart(cartOf(line(10, 7, 4)), cartOf(line(11, 7, 5)));

    act(() => {
      result.current.setCartQuantity(7, 5, { max: 5 });
      result.current.setCartQuantity(7, 6, { max: 5 });
    });
    expect(quantityOf(result.current.cart, 7)).toBe(5);

    await waitFor(() => expect(api.cart.update).toHaveBeenCalledTimes(1));
    expect(api.cart.update.mock.calls[0][1].quantity).toBe(5);
  });

  it('adds clicks on plus and minus up to one target quantity', async () => {
    api.cart.update.mockImplementation(async (_id: number, body: { quantity: number }) => ({
      item: line(11, 7, body.quantity),
      was_adjusted: false,
      message: '',
    }));
    const { result } = await renderCart(cartOf(line(10, 7, 1)), cartOf(line(11, 7, 3)));

    act(() => {
      void result.current.adjustCartQuantity(7, 1);
    });
    act(() => {
      void result.current.adjustCartQuantity(7, 1);
    });
    act(() => {
      void result.current.adjustCartQuantity(7, 1);
    });
    act(() => {
      void result.current.adjustCartQuantity(7, -1);
    });
    expect(quantityOf(result.current.cart, 7)).toBe(3);

    await waitFor(() => expect(api.cart.update).toHaveBeenCalled());
    await act(async () => {});
    expect(api.cart.update.mock.calls.map(([, body]) => body.quantity)).toEqual([3]);
    expect(api.cart.add).not.toHaveBeenCalled();
  });

  it('reloads the cart once the queue is idle and shows what the server has', async () => {
    api.cart.update.mockResolvedValue({ item: line(11, 7, 2), was_adjusted: false, message: '' });
    const { result } = await renderCart(cartOf(line(10, 7, 1)));
    // В другой вкладке тем временем положили ещё товар
    api.cart.get.mockResolvedValue(cartOf(line(11, 7, 2), line(30, 8, 1)));

    act(() => {
      result.current.setCartQuantity(7, 2);
    });

    await waitFor(() => expect(quantityOf(result.current.cart, 8)).toBe(1));
    expect(quantityOf(result.current.cart, 7)).toBe(2);
    expect(api.cart.get).toHaveBeenCalledTimes(2);
  });
});
