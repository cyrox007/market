import type { ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CategoryLandingV2 from './CategoryLandingV2';
import { resetSessionQueue } from '../../../lib/session-queue';
import type { Category, Product } from '../../../lib/api';

vi.mock('../../../hooks/useCounters', () => ({
  useCounters: () => ({ setCartCount: vi.fn(), bumpWishlistCount: vi.fn(), bumpCompareCount: vi.fn() }),
}));
vi.mock('../../../hooks/useRegion', () => ({ useRegion: () => ({ getRegionId: () => null }) }));
vi.mock('../../../contexts/cart-toast-context', () => ({ useCartToast: () => ({ showCartToast: vi.fn() }) }));

const api = vi.hoisted(() => ({
  cart: { get: vi.fn(), add: vi.fn(), update: vi.fn(), remove: vi.fn() },
  wishlist: { list: vi.fn(), add: vi.fn(), remove: vi.fn() },
  compare: { list: vi.fn(), add: vi.fn(), remove: vi.fn() },
  products: { get: vi.fn() },
}));
vi.mock('../../../lib/api', () => ({ api }));

const category = { id: 1, name: 'Диваны', slug: 'divany', children: [] } as unknown as Category;

/** Вариативный диван: в каталоге родитель 5, в корзине лежит вариант 51 */
const sofa = {
  id: 5,
  name: 'Диван «Доро»',
  slug: 'doro',
  price: 46210,
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
  api.wishlist.list.mockResolvedValue({ data: [] });
  api.compare.list.mockResolvedValue({ products: [] });
});

describe('CategoryLandingV2', () => {
  it('shows the cart quantity on a popular product whose variant is in the cart', async () => {
    api.cart.get.mockResolvedValue({
      items: [{ id: 9, product_id: 51, name: 'Диван «Доро», серый', slug: 'doro', price: 46210, quantity: 2, total: 92420, image: null, sku: null }],
      subtotal: 92420,
      item_count: 2,
      is_empty: false,
    });

    render(<CategoryLandingV2 category={category} products={[sofa]} isLoadingProducts={false} />, { wrapper });

    expect(await screen.findByRole('button', { name: 'Увеличить количество' })).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
  });
});
