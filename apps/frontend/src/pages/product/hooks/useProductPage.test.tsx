import { renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { MemoryRouter } from 'react-router-dom';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useProductPage } from './useProductPage';

const get = vi.fn();
const location = { params: {}, ready: true } as { params: object; ready: boolean };

vi.mock('../../../lib/api', () => ({ api: { products: { get: (...a: unknown[]) => get(...a) } } }));
const ssr: { value: unknown } = { value: null };
vi.mock('../../../contexts/ssr-context', () => ({ useSSR: () => ssr.value }));
vi.mock('../../../hooks/useLocationParams', () => ({ useLocationParams: () => location }));

/** Хук читает ?offer из адреса, поэтому живёт внутри роутера */
const routerAt =
  (url = '/product/tumba') =>
  ({ children }: { children: ReactNode }) => (
    <MemoryRouter initialEntries={[url]}>
      <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
    </MemoryRouter>
  );

const wrapper = routerAt();

describe('useProductPage: пункт справочника', () => {
  beforeEach(() => {
    get.mockReset().mockResolvedValue({ product: { slug: 'tumba' }, related: { data: [] } });
    ssr.value = null;
    location.params = {};
    location.ready = true;
  });

  it('шлёт region_id и directory_external_id', async () => {
    location.params = { region_id: 1, directory_external_id: 'lip' };
    location.ready = true;
    const { result } = renderHook(() => useProductPage('tumba'), { wrapper });
    await waitFor(() => expect(result.current.status).toBe('ready'));
    expect(get).toHaveBeenCalledWith('tumba', { region_id: 1, directory_external_id: 'lip' });
  });

  it('без SSR не запрашивает, пока пункт не прочитан', () => {
    location.ready = false;
    const { result } = renderHook(() => useProductPage('tumba'), { wrapper });
    expect(get).not.toHaveBeenCalled();
    expect(result.current.status).toBe('loading');
  });
});

describe('useProductPage: закрытая цена по ссылке', () => {
  beforeEach(() => {
    get.mockReset().mockResolvedValue({ product: { slug: 'tumba' }, related: { data: [] } });
    location.params = {};
    location.ready = true;
  });

  it('ответ сервера берётся, когда предложение в адресе и на сервере одно и то же', () => {
    ssr.value = { product: { product: { slug: 'tumba' } }, privateOfferContext: '5' };
    const { result } = renderHook(() => useProductPage('tumba'), {
      wrapper: routerAt('/product/tumba?offer=5'),
    });
    expect(result.current.status).toBe('ready');
    expect(get).not.toHaveBeenCalled();
  });

  it('обычный ответ сервера не подставляется под ссылку с предложением — цена запрашивается заново', async () => {
    ssr.value = { product: { product: { slug: 'tumba' } }, privateOfferContext: null };
    const { result } = renderHook(() => useProductPage('tumba'), {
      wrapper: routerAt('/product/tumba?offer=5'),
    });
    await waitFor(() => expect(get).toHaveBeenCalledTimes(1));
    expect(result.current.status).toBe('ready');
  });

  it('ответ по закрытой ссылке не подставляется на обычную карточку', async () => {
    ssr.value = { product: { product: { slug: 'tumba' } }, privateOfferContext: '5' };
    renderHook(() => useProductPage('tumba'), { wrapper: routerAt('/product/tumba') });
    await waitFor(() => expect(get).toHaveBeenCalledTimes(1));
  });
});
