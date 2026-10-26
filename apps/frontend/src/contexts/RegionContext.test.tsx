import { cleanup, render, screen, waitFor } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { RegionProvider } from './RegionContext';
import { useRegionContext } from './region-context';

afterEach(() => { cleanup(); localStorage.clear(); sessionStorage.clear(); vi.unstubAllGlobals(); });

it('restores the directory locality without loading the removed regions API', async () => {
  const fetch = vi.fn();
  vi.stubGlobal('fetch', fetch);
  localStorage.setItem('customer_locality', JSON.stringify({
    locality: { externalId: 'lipetsk-guid', name: 'Липецк', label: 'г. Липецк', source: 'gar' },
    shippingLocation: null,
  }));
  function Consumer() {
    const { locality, locationReady, getRegionId } = useRegionContext();
    return <span>{locationReady ? `${locality?.externalId}:${getRegionId()}` : 'loading'}</span>;
  }
  render(<RegionProvider><Consumer /></RegionProvider>);
  await waitFor(() => expect(screen.getByText('lipetsk-guid:null')).toBeTruthy());
  expect(fetch).not.toHaveBeenCalled();
});
