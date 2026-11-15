import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { SWRConfig } from 'swr';
import { renderToString } from 'react-dom/server';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { api } from '../../lib/api';
import Account from './page';

const { user, region } = vi.hoisted(() => ({
  user: { id: 1, name: 'Анна Смирнова', email: 'anna@example.test', phone: '+79103561842' },
  region: { locality: { name: 'Воронеж' }, locationReady: true },
}));
vi.mock('../../hooks/useAuth', () => ({ useAuth: () => ({ user, logout: vi.fn(), updateProfile: vi.fn() }) }));
vi.mock('../../hooks/useRegion', () => ({ useRegion: () => region }));
vi.mock('../../hooks/useCounters', () => ({ useCounters: () => ({ refreshWishlistCount: vi.fn(), wishlistCount: 4, compareCount: 2, isLoading: false }) }));
vi.mock('../../hooks/usePageSeo', () => ({ usePageSeo: vi.fn() }));
vi.mock('../../lib/api', () => ({ api: {
  orders: { list: vi.fn() }, addresses: { list: vi.fn() },
  auth: { getNotificationSettings: vi.fn() },
} }));

beforeEach(() => {
  vi.resetAllMocks();
  region.locality = { name: 'Воронеж' };
  region.locationReady = true;
  vi.mocked(api.orders.list).mockResolvedValue({ data: [] });
  vi.mocked(api.addresses.list).mockResolvedValue({ data: [] });
  vi.mocked(api.auth.getNotificationSettings).mockResolvedValue({ settings: {
    email_promotions: true, sms_order_notifications: true, push_notifications: false, new_product_notifications: true,
  } });
});
afterEach(cleanup);

function openProfile() {
  const cache = new Map();
  return render(<SWRConfig value={{ provider: () => cache, shouldRetryOnError: false }}>
    <MemoryRouter initialEntries={['/account']}><Account /></MemoryRouter>
  </SWRConfig>);
}

it('shows the city of the default saved address instead of the selected shopping locality', async () => {
  vi.mocked(api.addresses.list).mockResolvedValue({ data: [
    { id: 1, city: 'Москва', is_default: false },
    { id: 2, city: 'Краснодар', is_default: true },
  ] } as never);
  openProfile();
  const card = await screen.findByRole('region', { name: 'Профиль покупателя' });
  expect(await within(card).findByText('Краснодар')).toBeInTheDocument();
  expect(within(card).queryByText('Воронеж')).not.toBeInTheDocument();
  expect(within(card).queryByText('Москва')).not.toBeInTheDocument();
});

it('waits for saved addresses before falling back to the shopping locality', async () => {
  let finish!: (value: { data: [] }) => void;
  vi.mocked(api.addresses.list).mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
  openProfile();
  const card = screen.getByRole('region', { name: 'Профиль покупателя' });
  expect(within(card).getByRole('status', { name: 'Загрузка города' })).toBeInTheDocument();
  expect(within(card).queryByText('Воронеж')).not.toBeInTheDocument();
  await waitFor(() => expect(api.addresses.list).toHaveBeenCalled());
  await act(async () => { finish({ data: [] }); });
  expect(await within(card).findByText('Воронеж')).toBeInTheDocument();
});

it('uses the selected locality when the address directory fails and keeps a retry for the error', async () => {
  vi.mocked(api.addresses.list).mockRejectedValueOnce(new Error('offline'));
  openProfile();
  const card = screen.getByRole('region', { name: 'Профиль покупателя' });
  expect(await within(card).findByText('Воронеж')).toBeInTheDocument();
  expect(screen.getByRole('alert')).toHaveTextContent('Не удалось загрузить адреса');
});

it('does not use an arbitrary address when the default address has no city', async () => {
  vi.mocked(api.addresses.list).mockResolvedValue({ data: [
    { id: 1, city: 'Москва', is_default: false }, { id: 2, city: '  ', is_default: true },
  ] } as never);
  openProfile();
  const card = screen.getByRole('region', { name: 'Профиль покупателя' });
  expect(await within(card).findByText('Воронеж')).toBeInTheDocument();
  expect(within(card).queryByText('Москва')).not.toBeInTheDocument();
});

it('opens the existing profile editor and returns focus to the edit button when closed', async () => {
  openProfile();
  expect(screen.queryByText('Личные данные')).not.toBeInTheDocument();
  const edit = screen.getByRole('button', { name: 'Изменить профиль' });
  fireEvent.click(edit);
  expect(screen.getByLabelText('Редактирование профиля')).toHaveFocus();
  expect(screen.getByRole('heading', { name: 'Личные данные' })).toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Закрыть редактирование' }));
  expect(edit).toHaveFocus();
  expect(screen.queryByText('Личные данные')).not.toBeInTheDocument();
  await waitFor(() => expect(screen.queryByRole('status', { name: 'Загрузка рассылки' })).not.toBeInTheDocument());
});

it('renders the profile card during SSR without claiming a fallback city before addresses load', () => {
  const markup = renderToString(<SWRConfig value={{ provider: () => new Map() }}>
    <MemoryRouter initialEntries={['/account']}><Account /></MemoryRouter>
  </SWRConfig>);
  expect(markup).toContain('Профиль покупателя');
  expect(markup).toContain('Анна Смирнова');
  expect(markup).toContain('Загрузка города');
  expect(markup).not.toContain('Воронеж');
});

it('connects overview tiles to shared counters and opens settings through the new navigation', async () => {
  openProfile();
  const nav = screen.getByRole('navigation', { name: 'Разделы кабинета' });
  expect(within(nav).getByText('4 товара')).toBeInTheDocument();
  expect(within(nav).getByText('2 товара')).toBeInTheDocument();
  expect(await within(nav).findByText('0 адресов')).toBeInTheDocument();
  fireEvent.click(within(nav).getByRole('link', { name: 'Настройки профиля' }));
  expect(await screen.findByRole('heading', { name: 'Настройки уведомлений' })).toBeInTheDocument();
  expect(screen.queryByRole('navigation', { name: 'Разделы кабинета' })).not.toBeInTheDocument();
});
