import { renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useProductPage } from './useProductPage';

const get = vi.fn();
const location = { params: {}, ready: true } as { params: object; ready: boolean };

vi.mock('../../../lib/api', () => ({ api: { products: { get: (...a: unknown[]) => get(...a) } } }));
vi.mock('../../../contexts/ssr-context', () => ({ useSSR: () => null }));
vi.mock('../../../hooks/useLocationParams', () => ({ useLocationParams: () => location }));

const wrapper = ({ children }: { children: ReactNode }) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
);

describe('useProductPage: пункт справочника', () => {
  beforeEach(() => {
    get.mockReset().mockResolvedValue({ product: { slug: 'tumba' }, related: { data: [] } });
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
