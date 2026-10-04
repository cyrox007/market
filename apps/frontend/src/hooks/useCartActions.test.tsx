import type { ReactNode } from 'react';
import { act, renderHook, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useCartActions } from './useCartActions';
import { resetSessionQueue } from '../lib/session-queue';
import type { Product } from '../lib/api';

const showCartToast = vi.fn();

vi.mock('./useCounters', () => ({ useCounters: () => ({ setCartCount: vi.fn() }) }));
vi.mock('./useRegion', () => ({ useRegion: () => ({ getRegionId: () => null }) }));
vi.mock('../contexts/cart-toast-context', () => ({ useCartToast: () => ({ showCartToast }) }));

const api = vi.hoisted(() => ({
  cart: { get: vi.fn(), add: vi.fn(), update: vi.fn(), remove: vi.fn() },
}));
vi.mock('../lib/api', () => ({ api }));

const product = {
  id: 7,
  name: 'Стол',
  slug: 'stol',
  price: 100,
  in_stock: true,
  stock: 10,
  is_variable: false,
  is_variant: false,
  first_available_variant_id: null,
} as unknown as Product;

const wrapper = ({ children }: { children: ReactNode }) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
);

beforeEach(() => {
  vi.resetAllMocks();
  resetSessionQueue();
  api.cart.get.mockResolvedValue({ items: [], subtotal: 0, item_count: 0, is_empty: true });
});

describe('useCartActions', () => {
  it('sends "add to cart" and quick pluses as one request with the total', async () => {
    api.cart.add.mockImplementation(async (body: { product_id: number; quantity: number }) => ({
      item: { id: 55, product_id: 7, name: 'Стол', slug: 'stol', price: 100, quantity: body.quantity, total: 100 * body.quantity, image: null, sku: null },
      was_adjusted: false,
      message: '',
    }));
    api.cart.get
      .mockResolvedValueOnce({ items: [], subtotal: 0, item_count: 0, is_empty: true })
      .mockResolvedValue({
        items: [{ id: 55, product_id: 7, name: 'Стол', slug: 'stol', price: 100, quantity: 3, total: 300, image: null, sku: null }],
        subtotal: 300,
        item_count: 3,
        is_empty: false,
      });
    const { result } = renderHook(() => useCartActions(), { wrapper });
    await waitFor(() => expect(result.current.cart).not.toBeNull());

    act(() => {
      void result.current.addProductToCart(product, 1);
    });
    act(() => {
      void result.current.changeProductQuantity(product, 1);
    });
    act(() => {
      void result.current.changeProductQuantity(product, 1);
    });
    expect(result.current.cart?.items[0]?.quantity).toBe(3);

    await waitFor(() => expect(api.cart.add).toHaveBeenCalled());
    await act(async () => {});
    expect(api.cart.add.mock.calls.map(([body]) => body.quantity)).toEqual([3]);
    expect(result.current.cart?.items[0]?.id).toBe(55);
  });
});
