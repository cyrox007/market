import { cleanup, renderHook, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import { afterEach, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';
import { usePublicAuthSettings } from './usePublicAuthSettings';

const wrapper = ({ children }: { children: ReactNode }) => <SWRConfig value={{ provider: () => new Map() }}>{children}</SWRConfig>;
afterEach(() => { cleanup(); vi.unstubAllGlobals(); });

it('reads independent switches and requests fresh settings without cache', async () => {
  const request = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: { registration_enabled: false, login_enabled: true } }) });
  vi.stubGlobal('fetch', request);
  const { result } = renderHook(usePublicAuthSettings, { wrapper });
  await waitFor(() => expect(result.current.ready).toBe(true));
  expect(result.current.registrationEnabled).toBe(false);
  expect(result.current.loginEnabled).toBe(true);
  expect(request).toHaveBeenCalledWith(expect.stringContaining('/auth/settings'), expect.objectContaining({ cache: 'no-store' }));
});

it('does not enable either form if the settings response is unavailable', async () => {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));
  const { result } = renderHook(usePublicAuthSettings, { wrapper });
  await waitFor(() => expect(result.current.error).toBeTruthy());
  expect(result.current.ready).toBe(false);
  expect(result.current.loginEnabled).toBe(false);
  expect(result.current.registrationEnabled).toBe(false);
});
