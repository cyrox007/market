import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, useLocation, useNavigate } from 'react-router-dom';
import { afterEach, expect, it, vi } from 'vitest';
import { SWRConfig } from 'swr';
import { renderToString } from 'react-dom/server';
import { api } from '../../lib/api';
import Account from './page';

const { user, logout } = vi.hoisted(() => ({
  user: { id: 1, name: 'Покупатель', email: 'buyer@example.test' }, logout: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('../../hooks/useAuth', () => ({
  useAuth: () => ({ user, logout, updateProfile: vi.fn() }),
}));
vi.mock('../../hooks/useCounters', () => ({ useCounters: () => ({ refreshWishlistCount: vi.fn() }) }));
vi.mock('../../hooks/usePageSeo', () => ({ usePageSeo: vi.fn() }));
vi.mock('../../hooks/useRegion', () => ({ useRegion: () => ({ locality: null, locationReady: true }) }));
vi.mock('./AccountEmailSettings', () => ({ default: () => null }));
vi.mock('../../lib/api', () => ({ api: {
  addresses: { list: vi.fn().mockResolvedValue({ data: [] }) },
  orders: { list: vi.fn().mockResolvedValue({ data: [] }) },
  bonuses: { balance: vi.fn().mockResolvedValue({ balance: 0 }), history: vi.fn().mockResolvedValue({ data: [] }) },
  auth: { getNotificationSettings: vi.fn().mockResolvedValue({ settings: {
    email_promotions: false, sms_order_notifications: true, push_notifications: false, new_product_notifications: true,
  } }) },
} }));

function HistoryControls() {
  const location = useLocation();
  const navigate = useNavigate();
  return <><output aria-label="Текущий адрес">{location.pathname}{location.search}</output><button onClick={() => navigate(-1)}>Назад</button></>;
}

afterEach(cleanup);
vi.mock('../../hooks/useMediaQuery', () => ({ useMediaMatch: () => false }));

function openAccount(url: string) {
  const cache = new Map();
  return render(<SWRConfig value={{ provider: () => cache, dedupingInterval: 0, shouldRetryOnError: false }}>
    <MemoryRouter initialEntries={[url]}><HistoryControls /><Account /></MemoryRouter>
  </SWRConfig>);
}

it('opens saved addresses directly and keeps section changes in browser history', async () => {
  openAccount('/account?section=addresses&from=header');
  expect(await screen.findByRole('button', { name: 'Добавить новый адрес' })).toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Профиль' }));
  expect(screen.getByLabelText('Текущий адрес')).toHaveTextContent('/account?from=header');
  fireEvent.click(screen.getByRole('button', { name: 'Назад' }));
  expect(await screen.findByRole('button', { name: 'Добавить новый адрес' })).toBeInTheDocument();
  expect(screen.getByLabelText('Текущий адрес')).toHaveTextContent('section=addresses');
});

it.each([
  ['settings', 'Настройки уведомлений'],
  ['bonuses', 'История начислений'],
])('opens %s directly', async (section, title) => {
  openAccount('/account?section=' + section);
  expect(await screen.findByRole('heading', { name: title })).toBeInTheDocument();
});

it('replaces an unknown section with the profile without losing other query parameters', async () => {
  openAccount('/account?section=missing&from=header');
  expect(screen.getByRole('region', { name: 'Профиль покупателя' })).toBeInTheDocument();
  expect(await screen.findByText('/account?from=header')).toBeInTheDocument();
});

it('shows a retry instead of an empty address book when the request fails', async () => {
  vi.mocked(api.addresses.list).mockRejectedValueOnce(new Error('offline'));
  openAccount('/account?section=addresses');
  expect(await screen.findByRole('alert')).toHaveTextContent('Не удалось загрузить адреса');
  expect(screen.queryByText('У вас пока нет адресов доставки')).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Повторить' }));
  expect(await screen.findByText('У вас пока нет адресов доставки')).toBeInTheDocument();
});

it('loads the last order on the profile without opening the orders section first', async () => {
  vi.mocked(api.orders.list).mockResolvedValueOnce({ data: [{ id: 42, number: 'TEST-42' }] } as never);
  openAccount('/account');
  expect(await screen.findByRole('link', { name: 'Открыть заказ № TEST-42' })).toHaveAttribute('href', '/orders/42');
});

it('renders a direct address section on the server without browser storage', () => {
  const markup = renderToString(<SWRConfig value={{ provider: () => new Map() }}>
    <MemoryRouter initialEntries={['/account?section=addresses']}><Account /></MemoryRouter>
  </SWRConfig>);
  expect(markup).toContain('Добавить новый адрес');
  expect(markup).toContain('Загрузка адресов');
});

it('waits for account reads before signing out so session responses cannot race with logout', async () => {
  let finishOrders!: (value: { data: [] }) => void;
  const requestStarted = vi.fn();
  logout.mockClear();
  vi.mocked(api.orders.list).mockImplementationOnce(() => {
    requestStarted();
    return new Promise((resolve) => { finishOrders = resolve; });
  });
  openAccount('/account');

  await waitFor(() => expect(requestStarted).toHaveBeenCalled());
  fireEvent.click(screen.getByRole('link', { name: 'Настройки профиля' }));
  fireEvent.click(screen.getByRole('button', { name: 'Выйти' }));
  const calledWhileReading = logout.mock.calls.length;
  await act(async () => { finishOrders({ data: [] }); });
  expect(calledWhileReading).toBe(0);
  await screen.findByText('/login');
  expect(logout).toHaveBeenCalledTimes(1);
});
