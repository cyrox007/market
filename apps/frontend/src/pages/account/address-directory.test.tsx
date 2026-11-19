import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { SWRConfig } from 'swr';
import { afterEach, expect, it, vi } from 'vitest';
import Account from './page';
import { api } from '../../lib/api';

const { user } = vi.hoisted(() => ({ user: { id: 1, name: 'Покупатель', email: 'buyer@example.test', phone: '+79001234567' } }));
vi.mock('../../hooks/useAuth', () => ({ useAuth: () => ({ user, logout: vi.fn(), updateProfile: vi.fn() }) }));
vi.mock('../../hooks/useCounters', () => ({ useCounters: () => ({ refreshWishlistCount: vi.fn() }) }));
vi.mock('../../hooks/usePageSeo', () => ({ usePageSeo: vi.fn() }));
vi.mock('../../hooks/useRegion', () => ({ useRegion: () => ({ locality: null, locationReady: true }) }));
vi.mock('../../components/CheckoutAddress', () => ({ default: ({ onChange }: { onChange: (id: string) => void }) => <button type="button" onClick={() => onChange('house-guid')}>Выбрать дом из справочника</button> }));
vi.mock('./AccountEmailSettings', () => ({ default: () => null }));
vi.mock('../../lib/api', () => ({ api: {
  addresses: { list: vi.fn(), create: vi.fn(), update: vi.fn(), delete: vi.fn(), setDefault: vi.fn() },
  orders: { list: vi.fn().mockResolvedValue({ data: [] }) },
  auth: { getNotificationSettings: vi.fn().mockResolvedValue({ settings: { email_promotions: false } }) },
} }));
afterEach(cleanup);
vi.mock('../../hooks/useMediaQuery', () => ({ useMediaMatch: () => false }));
it('creates a saved address using the building ID, not legacy text or shipping location', async () => {
  vi.mocked(api.addresses.list).mockResolvedValue({ data: [] });
  vi.mocked(api.addresses.create).mockImplementation(async () => {
    vi.mocked(api.addresses.list).mockResolvedValue({ data: [{ id: 1, title: 'Новый дом', directory_external_id: 'house-guid' }] } as never);
    return { address: { id: 1 } } as never;
  });
  const cache = new Map();
  render(<SWRConfig value={{ provider: () => cache }}><MemoryRouter><Account /></MemoryRouter></SWRConfig>);
  fireEvent.click(screen.getByRole('link', { name: 'Адреса доставки' }));
  fireEvent.click(await screen.findByRole('button', { name: 'Добавить новый адрес' }));
  fireEvent.click(screen.getByText('Выбрать дом из справочника'));
  const buttons = screen.getAllByRole('button', { name: 'Добавить адрес' });
  fireEvent.click(buttons[buttons.length - 1]);
  await waitFor(() => expect(api.addresses.create).toHaveBeenCalledTimes(1));
  const body = vi.mocked(api.addresses.create).mock.calls[0][0];
  expect(body.directory_external_id).toBe('house-guid');
  expect(body).not.toHaveProperty('shipping_location_id');
  expect(body).not.toHaveProperty('city');
  expect(body).not.toHaveProperty('house');
  expect(await screen.findByText('Новый дом')).toBeInTheDocument();
});

it('reloads the address book after choosing a default address and deleting it', async () => {
  vi.mocked(api.addresses.list).mockResolvedValue({ data: [{ id: 3, title: 'Офис', is_default: false }] } as never);
  vi.mocked(api.addresses.setDefault).mockImplementation(async () => {
    vi.mocked(api.addresses.list).mockResolvedValue({ data: [{ id: 3, title: 'Офис', is_default: true }] } as never);
    return { message: 'OK' };
  });
  vi.mocked(api.addresses.delete).mockImplementation(async () => {
    vi.mocked(api.addresses.list).mockResolvedValue({ data: [] });
    return { message: 'OK' };
  });
  const cache = new Map();
  render(<SWRConfig value={{ provider: () => cache }}><MemoryRouter initialEntries={['/account?section=addresses']}><Account /></MemoryRouter></SWRConfig>);
  fireEvent.click(await screen.findByRole('button', { name: 'Сделать основным' }));
  expect(await screen.findByText('Основной')).toBeInTheDocument();
  expect(api.addresses.setDefault).toHaveBeenCalledWith(3);
  await waitFor(() => expect(screen.getByRole('button', { name: 'Удалить адрес Офис' })).toBeEnabled());
  fireEvent.click(screen.getByRole('button', { name: 'Удалить адрес Офис' }));
  expect(screen.getByRole('heading', { name: 'Удалить адрес?' })).toBeInTheDocument();
  const deleteButtons = screen.getAllByRole('button', { name: 'Удалить' });
  fireEvent.click(deleteButtons[deleteButtons.length - 1]);
  expect(await screen.findByText('У вас пока нет адресов доставки')).toBeInTheDocument();
  expect(api.addresses.delete).toHaveBeenCalledWith(3);
});

it('updates a saved address and displays the refreshed server response', async () => {
  vi.mocked(api.addresses.list).mockResolvedValue({ data: [{ id: 4, title: 'Старый заголовок', directory_external_id: 'house-guid' }] } as never);
  vi.mocked(api.addresses.update).mockImplementation(async () => {
    vi.mocked(api.addresses.list).mockResolvedValue({ data: [{ id: 4, title: 'Квартира', directory_external_id: 'house-guid' }] } as never);
    return { address: { id: 4 } } as never;
  });
  const cache = new Map();
  render(<SWRConfig value={{ provider: () => cache }}><MemoryRouter initialEntries={['/account?section=addresses']}><Account /></MemoryRouter></SWRConfig>);
  fireEvent.click(await screen.findByRole('button', { name: 'Редактировать адрес Старый заголовок' }));
  fireEvent.change(screen.getByLabelText('Название адреса'), { target: { value: 'Квартира' } });
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить адрес' }));
  expect(await screen.findByText('Квартира', { selector: 'h2' })).toBeInTheDocument();
  expect(api.addresses.update).toHaveBeenCalledWith(4, expect.objectContaining({ title: 'Квартира' }));
});
