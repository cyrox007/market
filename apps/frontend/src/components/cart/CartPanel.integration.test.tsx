import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { SWRConfig } from 'swr';
import { beforeEach, expect, it, vi } from 'vitest';
import CartPanel from './CartPanel';
import { CartPanelProvider } from '../../contexts/CartPanelContext';
import { useCartPanel } from '../../contexts/cart-panel-context';
import { resetSessionQueue } from '../../lib/session-queue';
import { deferred } from '../../test/deferred';
import type { Cart, CartItem } from '../../lib/api';

const api = vi.hoisted(() => ({ cart: { get: vi.fn(), update: vi.fn(), remove: vi.fn(), clear: vi.fn(), add: vi.fn() } }));
vi.mock('../../lib/api', () => ({ api }));
vi.mock('../../hooks/useCounters', () => ({ useCounters: () => ({ setCartCount: vi.fn() }) }));
vi.mock('../../hooks/useRegion', () => ({ useRegion: () => ({ getRegionId: () => null }) }));
vi.mock('../../hooks/useWishlistAndCompare', () => ({ useWishlistAndCompare: () => ({ wishlistProductIds: [], toggleWishlist: vi.fn() }) }));
vi.mock('../../hooks/usePrefetchProduct', () => ({ usePrefetchProduct: () => vi.fn() }));
vi.mock('../../hooks/useMediaQuery', () => ({ useMediaQuery: () => false }));
vi.mock('../../contexts/cart-toast-context', () => ({ useCartToast: () => ({ showCartToast: vi.fn() }) }));

function Harness() {
  const { openCart } = useCartPanel();
  return <><button onClick={openCart}>Открыть</button><CartPanel /></>;
}
beforeEach(() => { vi.resetAllMocks(); resetSessionQueue(); });

it('быстрые клики меняют только выбранную ценовую строку через настоящую очередь и SWR', async () => {
  const privateLine: CartItem = { id: 10, product_id: 7, name: 'Закрытая цена', slug: 'table', price: 60,
    total: 60, quantity: 1, image: null, sku: null, stock: 5, price_source: 'private_link' };
  const catalog: CartItem = { ...privateLine, id: 20, name: 'Каталог', price_source: 'catalog', price: 100, total: 100 };
  let server: Cart = { items: [privateLine, catalog], subtotal: 160, item_count: 2, is_empty: false };
  api.cart.get.mockImplementation(async () => server);
  const first = deferred<{ item: CartItem; was_adjusted: boolean; message: string }>();
  api.cart.update.mockReturnValueOnce(first.promise).mockImplementationOnce(async () => {
    server = { ...server, items: [{ ...privateLine, id: 12, quantity: 4, total: 240 }, catalog], subtotal: 340, item_count: 5 };
    return { item: server.items[0], was_adjusted: false, message: '' };
  });
  render(<SWRConfig value={{ provider: () => new Map() }}><MemoryRouter><CartPanelProvider><Harness /></CartPanelProvider></MemoryRouter></SWRConfig>);
  fireEvent.click(screen.getByText('Открыть'));
  const row = await screen.findByRole('article', { name: 'Закрытая цена' });
  fireEvent.click(within(row).getByRole('button', { name: 'Увеличить количество' }));
  await waitFor(() => expect(api.cart.update).toHaveBeenCalledTimes(1));
  fireEvent.click(within(row).getByRole('button', { name: 'Увеличить количество' }));
  fireEvent.click(within(row).getByRole('button', { name: 'Увеличить количество' }));
  // Общий остаток SKU = 5: четыре здесь и одна штука по цене каталога.
  fireEvent.click(within(row).getByRole('button', { name: 'Увеличить количество' }));
  expect(screen.getByTestId('cart-total')).toHaveTextContent('340 ₽');
  expect(within(screen.getByRole('article', { name: 'Каталог' })).getByRole('button', { name: 'Уменьшить количество' })).toBeDisabled();
  await act(async () => first.resolve({ item: { ...privateLine, id: 11, quantity: 2, total: 120 }, was_adjusted: false, message: '' }));
  await waitFor(() => expect(api.cart.update).toHaveBeenCalledTimes(2));
  expect(api.cart.update.mock.calls.map(([id, body]) => [id, body.quantity])).toEqual([[10, 2], [11, 4]]);
  await waitFor(() => expect(screen.getByTestId('cart-total')).toHaveTextContent('340 ₽'));
  expect(api.cart.add).not.toHaveBeenCalled();
});
