import { afterEach, expect, it, vi } from 'vitest';
import type { IncomingMessage } from 'node:http';
import { createSSRFetch } from './api-ssr';

afterEach(() => vi.unstubAllGlobals());
it('SSR sends private pricing context only for the offer URL, never because of a cookie alone', async () => {
  const fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => ({}) });
  vi.stubGlobal('fetch', fetch);
  await createSSRFetch({ url: '/product/chair?offer=7', headers: { cookie: 'has_private_offer=1; laravel_session=session' } } as IncomingMessage)('/products/chair');
  expect(fetch.mock.calls[0][1].headers['X-Private-Price-Context']).toBe('7');
  await createSSRFetch({ url: '/product/chair', headers: { cookie: 'has_private_offer=1; laravel_session=session' } } as IncomingMessage)('/products/chair');
  expect(fetch.mock.calls[1][1].headers['X-Private-Price-Context']).toBeUndefined();
});
