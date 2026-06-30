import { describe, expect, it, vi, beforeEach } from 'vitest';
import { createSSRApi } from './api-ssr';

describe('createSSRApi', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('uses sort_order and attributes[] in product list request', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: [], meta: {} }),
      headers: { get: () => 'application/json' },
    });
    vi.stubGlobal('fetch', fetchMock as any);

    const api = createSSRApi({ headers: { cookie: 'a=b' } } as any);
    await api.products.list({
      category_slug: 'chairs',
      sort_by: 'price',
      sort_order: 'asc',
      attributes: { material: ['wood'] },
      region_id: 10,
    });

    const calledUrl = String(fetchMock.mock.calls[0][0]);
    expect(calledUrl).toContain('/products?');
    expect(calledUrl).toContain('sort_by=price');
    expect(calledUrl).toContain('sort_order=asc');
    expect(calledUrl).toContain('attributes%5Bmaterial%5D%5B%5D=wood');
    expect(calledUrl).toContain('region_id=10');
    expect(calledUrl).not.toContain('&order=');
    expect(calledUrl).not.toContain('attr_material=');
  });
});
