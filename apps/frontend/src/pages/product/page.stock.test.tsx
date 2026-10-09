import { MemoryRouter } from 'react-router-dom';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SWRConfig } from 'swr';
import ProductPage from './page';

// Плашка наличия у вариативной карточки: productUtils настоящий, чтобы страница отрисовалась целиком.
const productsGet = vi.hoisted(() => vi.fn());

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

vi.mock('../../hooks/useWishlistAndCompare', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../../hooks/useWishlistAndCompare')>()),
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

const variant = (id: number, sku: string, slug: string, name: string, stock: number) => ({
  id,
  sku,
  price: 43180,
  old_price: null,
  stock,
  in_stock: stock > 0,
  variation_attributes: [
    { attribute_slug: 'variant', value_slug: slug, value_name: name, code: null },
  ],
  images: [],
  specifications: null,
  colors: [],
});

// Объединённая карточка: свой остаток и nearest_stock у карточки нулевые, у варианта остаток есть
const mergedProduct = {
  id: 536,
  name: 'Стенка МАРТА-11',
  slug: 'test-product',
  sku: 'VAR-536',
  price: 43180,
  old_price: null,
  is_variable: true,
  is_variant: false,
  in_stock: true,
  stock: 3,
  nearest_stock: 0,
  nearest_warehouse_name: null,
  stocks: [],
  backorder: false,
  images: [],
  specifications: [],
  variants: [variant(434, '158235', 'venge', 'Венге/Лоредо', 3)],
  variation_attributes: [
    {
      attribute_slug: 'variant',
      attribute_name: 'Вариант',
      type: 'string',
      values: [{ slug: 'venge', name: 'Венге/Лоредо', code: null }],
      is_multiple: false,
    },
  ],
  selected_variation: [
    { attribute_slug: 'variant', value_slug: 'venge', value_name: 'Венге/Лоредо', code: null },
  ],
};

describe('Product page — плашка наличия вариативной карточки', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('показывает «В наличии» по остатку выбранного варианта, а не по nearest_stock карточки', async () => {
    productsGet.mockResolvedValue({
      product: mergedProduct,
      variant_info: null,
      bundle: { data: [] },
      related: { data: [] },
    });
    renderPage();

    expect(await screen.findByRole('heading', { name: 'Стенка МАРТА-11' })).toBeInTheDocument();
    expect(await screen.findByText('В наличии')).toBeInTheDocument();
    expect(screen.queryByText('Нет в наличии')).toBeNull();
  });
});
