import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { SWRConfig } from 'swr';
import { renderToString } from 'react-dom/server';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { api, type Order } from '../../lib/api';
import Orders from './page';

vi.mock('../../lib/api', () => ({ api: { orders: { list: vi.fn() } } }));
vi.mock('../../hooks/useAuth', () => ({ useAuth: () => ({ user: { id: 1 } }) }));
vi.mock('../../hooks/usePageSeo', () => ({ usePageSeo: vi.fn() }));

const orders = [
  { id: 1, number: 'NEW', status: 'new', created_at: '2026-11-22', total: 100, presentation: { group: 'active', status: { code: 'new', label: 'Ожидает передачи' } } },
  { id: 2, number: 'DONE', status: 'delivered', created_at: '2026-11-21', total: 200 },
  { id: 3, number: 'CANCEL', status: 'cancelled', created_at: '2026-11-20', total: 300 },
] as Order[];
function Location() { return <output aria-label="Адрес">{useLocation().search}</output>; }
function open(url = '/orders') {
  return render(<SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0, shouldRetryOnError: false }}><MemoryRouter initialEntries={[url]}><Location /><Orders /></MemoryRouter></SWRConfig>);
}
beforeEach(() => { vi.resetAllMocks(); vi.mocked(api.orders.list).mockResolvedValue({ data: orders }); });
afterEach(cleanup);

it('filters by server groups, counts future statuses as active and keeps cancelled out of completed', async () => {
  open('/orders?from=profile');
  expect(await screen.findByRole('link', { name: 'Открыть заказ № NEW' })).toHaveAttribute('href', '/orders/1');
  expect(screen.getByText('Ожидает передачи')).toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Активные 1' }));
  expect(screen.getByLabelText('Адрес')).toHaveTextContent('from=profile&group=active');
  expect(screen.queryByRole('link', { name: 'Открыть заказ № DONE' })).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Завершённые 1' }));
  expect(screen.getByRole('link', { name: 'Открыть заказ № DONE' })).toBeInTheDocument();
  expect(screen.queryByRole('link', { name: 'Открыть заказ № CANCEL' })).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Все 3' }));
  expect(screen.getByRole('link', { name: 'Открыть заказ № CANCEL' })).toBeInTheDocument();
});

it('opens a filter directly and shows an empty filter without claiming history is empty', async () => {
  vi.mocked(api.orders.list).mockResolvedValue({ data: [orders[2]] });
  open('/orders?group=completed');
  expect(await screen.findByText('Завершённых заказов пока нет')).toBeInTheDocument();
  expect(screen.queryByText('У вас пока нет заказов')).not.toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Все 1' })).toBeInTheDocument();
});

it('distinguishes loading, failure, retry and confirmed empty history', async () => {
  let reject!: (reason: Error) => void;
  vi.mocked(api.orders.list).mockImplementationOnce(() => new Promise((_, fail) => { reject = fail; }));
  open();
  expect(screen.getByRole('status', { name: 'Загрузка заказов' })).toBeInTheDocument();
  expect(screen.queryByText('У вас пока нет заказов')).not.toBeInTheDocument();
  await vi.waitFor(() => expect(reject).toBeTypeOf('function'));
  reject(new Error('offline'));
  expect(await screen.findByRole('alert')).toHaveTextContent('Не удалось загрузить заказы');
  vi.mocked(api.orders.list).mockResolvedValue({ data: [] });
  fireEvent.click(within(screen.getByRole('alert')).getByRole('button', { name: 'Повторить' }));
  expect(await screen.findByText('У вас пока нет заказов')).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Перейти в каталог' })).toHaveAttribute('href', '/catalog');
});

it('renders a loading state during SSR without issuing a session request', () => {
  const markup = renderToString(<SWRConfig value={{ provider: () => new Map() }}><MemoryRouter><Orders /></MemoryRouter></SWRConfig>);
  expect(markup).toContain('Загрузка заказов');
  expect(api.orders.list).not.toHaveBeenCalled();
});
