import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, expect, it, vi } from 'vitest';
import Account from './page';
import { api } from '../../lib/api';

const { user } = vi.hoisted(() => ({ user: { id: 1, name: 'Покупатель', email: 'buyer@example.test', phone: '+79001234567' } }));
vi.mock('../../hooks/useAuth', () => ({ useAuth: () => ({ user, logout: vi.fn(), updateProfile: vi.fn() }) }));
vi.mock('../../hooks/useCounters', () => ({ useCounters: () => ({ refreshWishlistCount: vi.fn() }) }));
vi.mock('../../hooks/usePageSeo', () => ({ usePageSeo: vi.fn() }));
vi.mock('../../components/CheckoutAddress', () => ({ default: ({ onChange }: { onChange: (id: string) => void }) => <button type="button" onClick={() => onChange('house-guid')}>Выбрать дом из справочника</button> }));
vi.mock('../../lib/api', () => ({ api: { addresses: { list: vi.fn(), create: vi.fn() } } }));
afterEach(cleanup);
it('creates a saved address using the building ID, not legacy text or shipping location', async () => {
  vi.mocked(api.addresses.list).mockResolvedValue({ data: [] });
  vi.mocked(api.addresses.create).mockResolvedValue({ address: { id: 1 } } as never);
  render(<MemoryRouter><Account /></MemoryRouter>);
  fireEvent.click(screen.getByRole('button', { name: 'Адреса доставки' }));
  fireEvent.click(await screen.findByRole('button', { name: 'Добавить адрес' }));
  fireEvent.click(screen.getByText('Выбрать дом из справочника'));
  const buttons = screen.getAllByRole('button', { name: 'Добавить адрес' });
  fireEvent.click(buttons[buttons.length - 1]);
  await waitFor(() => expect(api.addresses.create).toHaveBeenCalledTimes(1));
  const body = vi.mocked(api.addresses.create).mock.calls[0][0];
  expect(body.directory_external_id).toBe('house-guid');
  expect(body).not.toHaveProperty('shipping_location_id');
  expect(body).not.toHaveProperty('city');
  expect(body).not.toHaveProperty('house');
});
