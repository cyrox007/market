import { afterEach, describe, expect, it, vi } from 'vitest';
import { api } from './api';

const ok = (body: unknown) => ({
  ok: true,
  status: 200,
  json: async () => body,
  headers: { get: () => 'application/json' },
});
const unavailable = () => ({
  ok: false,
  status: 503,
  json: async () => ({ message: 'Адресный справочник временно недоступен.' }),
  headers: { get: () => 'application/json' },
});
const urls = (fetchMock: ReturnType<typeof vi.fn>) => fetchMock.mock.calls.map(([url]) => String(url));

describe('пункт справочника в карточке и на главной', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('карточка товара — region_id и directory_external_id', async () => {
    const fetchMock = vi.fn().mockResolvedValue(ok({ product: {} }));
    vi.stubGlobal('fetch', fetchMock);
    await api.products.get('divan', { region_id: 7, directory_external_id: 'eacb5f15' });
    expect(urls(fetchMock)[0]).toContain('/products/divan?region_id=7&directory_external_id=eacb5f15');
  });

  it('хиты на главной — region_id и directory_external_id', async () => {
    const fetchMock = vi.fn().mockResolvedValue(ok({ data: [] }));
    vi.stubGlobal('fetch', fetchMock);
    await api.products.featured({ region_id: 7, directory_external_id: 'eacb5f15' });
    expect(urls(fetchMock)[0]).toContain('/products/featured?region_id=7&directory_external_id=eacb5f15');
  });

  it('справочник недоступен (503) — повтор без пункта', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(unavailable())
      .mockResolvedValue(ok({ product: {} }));
    vi.stubGlobal('fetch', fetchMock);
    await api.products.get('divan', { region_id: 7, directory_external_id: 'eacb5f15' });
    expect(urls(fetchMock)[1]).toMatch(/\/products\/divan\?region_id=7$/);
  });
});
