import type { ReactNode } from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ProductCardConnected from './ProductCardConnected';
import { resetSessionQueue } from '../../lib/session-queue';
import type { Product } from '../../lib/api';

vi.mock('../../hooks/useCounters', () => ({
  useCounters: () => ({ setCartCount: vi.fn(), bumpWishlistCount: vi.fn(), bumpCompareCount: vi.fn() }),
}));
vi.mock('../../hooks/useRegion', () => ({ useRegion: () => ({ getRegionId: () => null }) }));
vi.mock('../../contexts/cart-toast-context', () => ({ useCartToast: () => ({ showCartToast: vi.fn() }) }));

const api = vi.hoisted(() => ({
  cart: { get: vi.fn(), add: vi.fn(), update: vi.fn(), remove: vi.fn() },
  wishlist: { list: vi.fn(), add: vi.fn(), remove: vi.fn() },
  compare: { list: vi.fn(), add: vi.fn(), remove: vi.fn() },
  products: { get: vi.fn() },
}));
vi.mock('../../lib/api', () => ({ api }));

/** Вариативный диван: в каталоге — родитель 5, в корзину кладётся вариант 51 */
const sofa = {
  id: 5,
  name: 'Диван «Классик»',
  slug: 'klassik',
  price: 45000,
  in_stock: true,
  stock: 10,
  is_variable: true,
  is_variant: false,
  first_available_variant_id: 51,
} as unknown as Product;

const wrapper = ({ children }: { children: ReactNode }) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>
    <MemoryRouter>{children}</MemoryRouter>
  </SWRConfig>
);

beforeEach(() => {
  vi.resetAllMocks();
  resetSessionQueue();
  api.cart.get.mockResolvedValue({ items: [], subtotal: 0, item_count: 0, is_empty: true });
  api.wishlist.list.mockResolvedValue({ data: [] });
  api.compare.list.mockResolvedValue({ products: [] });
});

describe('ProductCardConnected', () => {
  it('shows the quantity when a variant of the product is in the cart', async () => {
    api.cart.get.mockResolvedValue({
      items: [{ id: 9, product_id: 51, name: 'Диван «Классик», серый', slug: 'klassik', price: 45000, quantity: 2, total: 90000, image: null, sku: null }],
      subtotal: 90000,
      item_count: 2,
      is_empty: false,
    });

    render(<ProductCardConnected product={sofa} />, { wrapper });

    expect(await screen.findByRole('button', { name: 'Увеличить количество' })).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'В корзину' })).not.toBeInTheDocument();
  });

  it('marks favorites by the product and compare by its variant, and toggles them', async () => {
    api.wishlist.list.mockResolvedValue({ data: [{ product_id: 5 }] });
    api.wishlist.remove.mockResolvedValue({});
    api.compare.add.mockResolvedValue({});

    render(<ProductCardConnected product={sofa} />, { wrapper });

    fireEvent.click(await screen.findByRole('button', { name: 'Удалить из избранного' }));
    fireEvent.click(screen.getByRole('button', { name: 'Добавить в сравнение' }));

    expect(screen.getByRole('button', { name: 'Добавить в избранное' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Удалить из сравнения' })).toBeInTheDocument();
    await waitFor(() => expect(api.compare.add).toHaveBeenCalledWith(51));
    expect(api.wishlist.remove).toHaveBeenCalledWith(5);
  });

  it('starts loading the product page on hover', async () => {
    api.products.get.mockResolvedValue({});

    render(<ProductCardConnected product={sofa} />, { wrapper });
    fireEvent.mouseEnter(screen.getByRole('link', { name: 'Диван «Классик»' }).closest('.group')!);

    expect(api.products.get).toHaveBeenCalledWith('klassik', expect.anything());
  });
});
