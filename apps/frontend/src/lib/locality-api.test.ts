import { afterEach, expect, it, vi } from 'vitest';
import { api } from './api';

afterEach(() => vi.unstubAllGlobals());
it('loads directory regions through the current locality API', async () => {
  const data = [{ externalId: 'region-guid', name: 'Липецкая', label: 'обл. Липецкая' }];
  const fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data }) });
  vi.stubGlobal('fetch', fetch);
  expect(await api.addressDirectory.regions()).toEqual({ data });
  expect(fetch.mock.calls[0][0]).toMatch(/\/api\/v1\/localities\/regions$/);
});
