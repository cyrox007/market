import { MemoryRouter } from 'react-router-dom';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SWRConfig } from 'swr';
import ProductPage from './page';

// Управляем поведением запроса из каждого теста отдельно.
const productsGet = vi.hoisted(() => vi.fn());

/** Ошибка в том виде, в каком её бросает клиент API: со статусом ответа. */
const apiError = (status: number) => Object.assign(new Error(`API Error: ${status}`), { status });

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
      get: productsGet,
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

// Своё хранилище SWR на каждый рендер, иначе тесты переиспользуют
// результат друг друга: ключ запроса у них одинаковый.
const renderPage = () =>
  render(
    <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>
      <MemoryRouter>
        <ProductPage />
      </MemoryRouter>
    </SWRConfig>,
  );

describe('Product page', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('404 — товар не найден', async () => {
    productsGet.mockRejectedValue(apiError(404));
    renderPage();

    expect(await screen.findByText('Товар не найден')).toBeInTheDocument();
  });

  it('ошибка сервера — не «не найден», а «не удалось загрузить»', async () => {
    productsGet.mockRejectedValue(apiError(500));
    renderPage();

    expect(await screen.findByText('Не удалось загрузить товар')).toBeInTheDocument();
    expect(screen.queryByText('Товар не найден')).toBeNull();
  });

  it('сбой сети — тоже «не удалось загрузить», а не бесконечный скелетон', async () => {
    productsGet.mockRejectedValue(new TypeError('Failed to fetch'));
    renderPage();

    expect(await screen.findByText('Не удалось загрузить товар')).toBeInTheDocument();
  });
});
