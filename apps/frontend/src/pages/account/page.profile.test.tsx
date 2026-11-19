import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { SWRConfig } from 'swr';
import { renderToString } from 'react-dom/server';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { api } from '../../lib/api';
import Account from './page';

const { user, region, updateProfile } = vi.hoisted(() => ({
  user: { id: 1, name: 'Анна Смирнова', email: 'anna@example.test', phone: '+79103561842' },
  updateProfile: vi.fn(),
  region: { locality: { name: 'Воронеж' }, locationReady: true },
}));
vi.mock('../../hooks/useAuth', () => ({ useAuth: () => ({ user, logout: vi.fn(), updateProfile }) }));
vi.mock('../../hooks/useRegion', () => ({ useRegion: () => region }));
vi.mock('../../hooks/useCounters', () => ({ useCounters: () => ({ refreshWishlistCount: vi.fn(), wishlistCount: 4, compareCount: 2, isLoading: false }) }));
vi.mock('../../hooks/usePageSeo', () => ({ usePageSeo: vi.fn() }));
vi.mock('./AccountEmailSettings', () => ({ default: () => null }));
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
    email_promotions: true, email_order_notifications: true, sms_order_notifications: true, push_notifications: false, new_product_notifications: true,
  } });
});
afterEach(cleanup);

function openProfile() {
  const cache = new Map();
  return render(<SWRConfig value={{ provider: () => cache, shouldRetryOnError: false }}>
    <MemoryRouter initialEntries={['/account']}><Account /></MemoryRouter>
  </SWRConfig>);
}

it('shows contacts without the default address or shopping city', async () => {
  vi.mocked(api.addresses.list).mockResolvedValue({ data: [
    { id: 1, city: 'Москва', is_default: false },
    { id: 2, city: 'Краснодар', is_default: true },
  ] } as never);
  openProfile();
  const card = await screen.findByRole('region', { name: 'Профиль покупателя' });
  expect(within(card).queryByText('Краснодар')).not.toBeInTheDocument();
  expect(within(card).queryByText('Воронеж')).not.toBeInTheDocument();
  expect(within(card).queryByText('Москва')).not.toBeInTheDocument();
  await waitFor(() => expect(screen.queryByRole('status', { name: 'Загрузка рассылки' })).not.toBeInTheDocument());
});

it('shows contacts while saved addresses are pending', async () => {
  let finish!: (value: { data: [] }) => void;
  vi.mocked(api.addresses.list).mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
  openProfile();
  const card = screen.getByRole('region', { name: 'Профиль покупателя' });
  expect(within(card).queryByRole('status', { name: 'Загрузка города' })).not.toBeInTheDocument();
  expect(within(card).queryByText('Воронеж')).not.toBeInTheDocument();
  await waitFor(() => expect(api.addresses.list).toHaveBeenCalled());
  await act(async () => { finish({ data: [] }); });
  expect(within(card).getByText('anna@example.test')).toBeInTheDocument();
  expect(within(card).queryByText('Воронеж')).not.toBeInTheDocument();
});

it('keeps contacts and an address retry when saved addresses fail', async () => {
  vi.mocked(api.addresses.list).mockRejectedValueOnce(new Error('offline'));
  openProfile();
  const card = screen.getByRole('region', { name: 'Профиль покупателя' });
  expect(within(card).getByText('anna@example.test')).toBeInTheDocument();
  expect(within(card).queryByText('Воронеж')).not.toBeInTheDocument();
  expect(await screen.findByRole('alert')).toHaveTextContent('Не удалось загрузить адреса');
});

it('does not use an arbitrary address when the default address has no city', async () => {
  vi.mocked(api.addresses.list).mockResolvedValue({ data: [
    { id: 1, city: 'Москва', is_default: false }, { id: 2, city: '  ', is_default: true },
  ] } as never);
  openProfile();
  const card = screen.getByRole('region', { name: 'Профиль покупателя' });
  expect(within(card).getByText('anna@example.test')).toBeInTheDocument();
  expect(within(card).queryByText('Воронеж')).not.toBeInTheDocument();
  expect(within(card).queryByText('Москва')).not.toBeInTheDocument();
  await waitFor(() => expect(screen.queryByRole('status', { name: 'Загрузка рассылки' })).not.toBeInTheDocument());
});

it('opens the same settings section from edit profile and the settings tile', async () => {
  openProfile();
  fireEvent.click(screen.getByRole('button', { name: 'Изменить профиль' }));
  expect(await screen.findByRole('form', { name: 'Личные данные' })).toBeInTheDocument();
  expect(screen.getByRole('heading', { name: 'Настройки профиля', level: 1 })).toHaveFocus();
  fireEvent.click(screen.getByRole('button', { name: 'Профиль' }));
  fireEvent.click(screen.getByRole('link', { name: 'Настройки профиля' }));
  expect(await screen.findByRole('form', { name: 'Личные данные' })).toBeInTheDocument();
});
it('renders the profile card during SSR without claiming a fallback city before addresses load', () => {
  const markup = renderToString(<SWRConfig value={{ provider: () => new Map() }}>
    <MemoryRouter initialEntries={['/account']}><Account /></MemoryRouter>
  </SWRConfig>);
  expect(markup).toContain('Профиль покупателя');
  expect(markup).toContain('Анна Смирнова');
  expect(markup).not.toContain('Загрузка города');
  expect(markup).not.toContain('Воронеж');
});

it('saves separate name fields through the new profile card editor', async () => {
  openProfile();
  fireEvent.click(screen.getByRole('button', { name: 'Изменить профиль' }));
  fireEvent.change(screen.getByLabelText(/^Имя/), { target: { value: 'Анна' } });
  fireEvent.change(screen.getByLabelText(/^Фамилия/), { target: { value: 'Смирнова' } });
  fireEvent.change(screen.getByLabelText(/^Отчество/), { target: { value: 'Игоревна' } });
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  await waitFor(() => expect(updateProfile).toHaveBeenCalledExactlyOnceWith({
    first_name: 'Анна', last_name: 'Смирнова', patronymic: 'Игоревна', phone: '+79103561842',
  }));
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

it('shows working email controls and hides unsupported channels', async () => {
  openProfile();
  fireEvent.click(screen.getByRole('link', { name: 'Настройки профиля' }));
  expect(await screen.findByRole('checkbox', { name: 'Статус заказа на email' })).toBeChecked();
  expect(screen.getByRole('checkbox', { name: /Акции и скидки на email/ })).toBeChecked();
  expect(screen.getByText('Не чаще одного раза в неделю')).toBeInTheDocument();
  expect(screen.queryByText('SMS-уведомления о заказах')).not.toBeInTheDocument();
  expect(screen.queryByText('Push-уведомления')).not.toBeInTheDocument();
  expect(screen.queryByText('Уведомления о новинках')).not.toBeInTheDocument();
});
