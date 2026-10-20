import { MemoryRouter } from 'react-router-dom';
import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { SWRConfig } from 'swr';
import ProductPage from './page';

// Сервер принял соединение и молчит: fetchAPI обрывает запрос через 20 с, страница показывает ошибку.
// lib/api НЕ мокается: идём через настоящий fetchAPI, подменён только глобальный fetch.

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<any>('react-router-dom');
  return { ...actual, useParams: () => ({ slug: 'test-product' }), useNavigate: () => vi.fn() };
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
  useCounters: () => ({ refreshWishlistCount: vi.fn(), refreshCompareCount: vi.fn() }),
}));
vi.mock('../../hooks/useRegion', () => ({
  useRegion: () => ({ region: { id: 1 }, getRegionId: () => 1 }),
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
vi.mock('../../contexts/SSRContext', () => ({ useSSR: () => ({}) }));
vi.mock('../../hooks/usePageSeo', () => ({ usePageSeo: vi.fn() }));

const productUrls: string[] = [];

beforeEach(() => {
  productUrls.length = 0;
  vi.useFakeTimers({
    toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'],
  });
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      if (/\/products\/test-product(\?|$)/.test(url)) {
        productUrls.push(url);
        // Соединение принято, ответа нет; уважаем только abort, как настоящий fetch
        return new Promise<Response>((_resolve, reject) => {
          init?.signal?.addEventListener('abort', () =>
            reject(new DOMException('The operation was aborted.', 'AbortError')),
          );
        });
      }
      return Promise.resolve(
        new Response(JSON.stringify({ data: [] }), {
          status: 200,
          headers: { 'content-type': 'application/json' },
        }),
      );
    }),
  );
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
});

describe('Product page — сервер принял соединение и молчит', () => {
  it('через 30 с вместо скелетона показывает «Не удалось загрузить товар»', async () => {
    const { container } = render(
      <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>
        <MemoryRouter>
          <ProductPage />
        </MemoryRouter>
      </SWRConfig>,
    );

    await act(async () => {
      await vi.advanceTimersByTimeAsync(30_000);
    });

    // Предусловие: запрос товара реально ушёл через fetchAPI
    expect(productUrls.length).toBeGreaterThan(0);
    // Фактическое состояние фиксируем в сообщении: скелетон на месте?
    const skeletonShown = container.querySelector('.animate-pulse') !== null;
    expect(
      screen.queryByText('Не удалось загрузить товар') !== null,
      `через 30 с ошибки нет; скелетон показан: ${skeletonShown}`,
    ).toBe(true);
  });
});
