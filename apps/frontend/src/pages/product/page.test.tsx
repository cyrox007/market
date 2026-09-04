import { MemoryRouter } from 'react-router-dom';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import ProductPage from './page';

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<any>('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ slug: 'test-product' }),
    useNavigate: () => vi.fn(),
  };
});

vi.mock('../../components/feature/ReviewModal', () => ({ default: () => null }));
vi.mock('../../components/ui/ProductLink', () => ({
  default: ({ children }: any) => <>{children}</>,
}));
vi.mock('../../components/ui/ProductCard', () => ({ default: () => null }));
vi.mock('../../components/product/ProductGallery', () => ({ default: () => null }));
vi.mock('../../components/product/VariantAttributeSelector', () => ({ default: () => null }));

vi.mock('../../hooks/useCart', () => ({
  useCart: () => ({
    cart: { items: [] },
    addToCart: vi.fn(),
    updateQuantity: vi.fn(),
    removeFromCart: vi.fn(),
  }),
}));

vi.mock('../../hooks/useCounters', () => ({
  useCounters: () => ({
    refreshWishlistCount: vi.fn(),
    refreshCompareCount: vi.fn(),
  }),
}));

vi.mock('../../hooks/useRegion', () => ({
  useRegion: () => ({
    region: { id: 1 },
    getRegionId: () => 1,
  }),
}));

vi.mock('../../hooks/useWishlistAndCompare', () => ({
  useWishlistAndCompare: () => ({
    wishlistProductIds: [],
    compareProductIds: [],
    mutateWishlist: vi.fn(),
    mutateCompare: vi.fn(),
  }),
}));

vi.mock('../../contexts/SSRContext', () => ({
  useSSR: () => ({}),
}));

vi.mock('../../hooks/usePageSeo', () => ({
  usePageSeo: vi.fn(),
}));

vi.mock('../../utils/productUtils', () => ({
  formatStockCategory: () => 'мало',
  getProductStockStatus: () => ({ stock: 0, available: false, backorder: false }),
}));

vi.mock('../../lib/api', () => ({
  api: {
    products: {
      get: vi.fn(async () => {
        throw new Error('not found');
      }),
      related: vi.fn(async () => ({ data: [] })),
      bundle: vi.fn(async () => ({ data: [] })),
    },
    reviews: {
      list: vi.fn(async () => ({ data: [] })),
    },
    stockSettings: {
      get: vi.fn(async () => ({
        stock_settings: {
          stock_low_max: 1,
          stock_medium_max: 5,
          stock_high_max: 10,
          show_exact_above: 10,
        },
      })),
    },
    wishlist: {
      toggle: vi.fn(async () => ({ in_wishlist: false })),
    },
    compare: {
      add: vi.fn(),
      remove: vi.fn(),
    },
  },
}));

describe('Product page', () => {
  it('shows not found state when product is unavailable', async () => {
    render(
      <MemoryRouter>
        <ProductPage />
      </MemoryRouter>,
    );

    expect(await screen.findByText('Товар не найден')).toBeInTheDocument();
  });
});
