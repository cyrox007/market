import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, expect, it } from 'vitest';
import { RegionProvider } from './RegionContext';
import { useRegionContext } from './region-context';
const city = { externalId: '01234567-1234-1234-1234-012345678901', name: 'Посёлок', label: 'Область, Посёлок', source: 'gar' };
const region = { id: 17, name: 'Область', slug: 'oblast', type: 'region' as const, parent_id: null };
function Probe() {
  const ctx = useRegionContext();
  return <><span>{ctx.locality?.name ?? 'Нет города'} / {ctx.getRegionId() ?? 'Нет зоны'}</span><button onClick={() => ctx.selectLocality(city, null)}>Выбрать</button></>;
}
beforeEach(() => { localStorage.clear(); sessionStorage.clear(); });
afterEach(cleanup);
it('stores a settlement independently and does not restore a stale warehouse location', async () => {
  localStorage.setItem('selected_region_data', JSON.stringify(region));
  localStorage.setItem('selected_region_id', '17');
  const view = render(<RegionProvider initialRegions={[region]} initialRegion={region}><Probe /></RegionProvider>);
  fireEvent.click(screen.getByText('Выбрать'));
  await waitFor(() => expect(screen.getByText('Посёлок / Нет зоны')).toBeInTheDocument());
  expect(localStorage.getItem('selected_region_id')).toBeNull();
  view.unmount();
  render(<RegionProvider initialRegions={[region]} initialRegion={region}><Probe /></RegionProvider>);
  await waitFor(() => expect(screen.getByText('Посёлок / Нет зоны')).toBeInTheDocument());
});
