import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { renderToString } from 'react-dom/server';
import { afterEach, expect, it, vi } from 'vitest';
import type { Order } from '../../lib/api';
import AccountLastOrder from './AccountLastOrder';

afterEach(cleanup);
const order = {
  id: 42, number: '48217', status: 'shipped', status_label: 'Отправлен', total: 40700,
  created_at: '2026-09-02T10:00:00Z',
  items: [
    { id: 1, product_id: 10, quantity: 2, price: 14170, total: 28340, product: { id: 10, name: 'Кухня Нагано', image: '/kitchen.png', price: 999999 } },
    { id: 2, product_id: 11, quantity: 1, price: 25330, total: 25330, product: { id: 11, name: 'Диван Эконом', thumbnail: '/sofa.png' } },
  ],
} as unknown as Order;

it('shows the latest returned order, saved total and total units with links to details and history', () => {
  render(<MemoryRouter><AccountLastOrder orders={[order]} isLoading={false} onRetry={vi.fn()} /></MemoryRouter>);
  expect(screen.getByRole('region', { name: 'Последний заказ' })).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Все заказы' })).toHaveAttribute('href', '/orders');
  expect(screen.getByRole('link', { name: 'Открыть заказ № 48217' })).toHaveAttribute('href', '/orders/42');
  expect(screen.getByText('Заказ № 48217')).toBeInTheDocument();
  expect(screen.getByText(/от 2 сентября 2026, 3 товара/)).toBeInTheDocument();
  expect(screen.getByText('Кухня Нагано, Диван Эконом')).toBeInTheDocument();
  expect(screen.getByText(/40\s700\s₽/)).toBeInTheDocument();
  expect(screen.getByText('Отправлен')).toBeInTheDocument();
  expect(screen.getByRole('img', { name: 'Кухня Нагано' })).toHaveAttribute('src', '/kitchen.png');
});

it('uses placeholders for removed or broken products and counts hidden positions rather than units', () => {
  const missing = { id: 3, product_id: 12, quantity: 4, price: 10, total: 40, product: null };
  const extra = { ...order, items: [order.items![0], missing, { ...missing, id: 4 }, { ...missing, id: 5 }, { ...missing, id: 6 }] };
  render(<MemoryRouter><AccountLastOrder orders={[extra]} isLoading={false} onRetry={vi.fn()} /></MemoryRouter>);
  expect(screen.getByText('+3')).toBeInTheDocument();
  expect(screen.getByRole('img', { name: 'Нет изображения: Товар #12' })).toBeInTheDocument();
  fireEvent.error(screen.getByRole('img', { name: 'Кухня Нагано' }));
  expect(screen.getByRole('img', { name: 'Нет изображения: Кухня Нагано' })).toBeInTheDocument();
  expect(screen.queryByRole('img', { name: 'Кухня Нагано' })).not.toBeInTheDocument();
});

it('shows neutral unknown status and missing date without inventing payment or a sum', () => {
  render(<MemoryRouter><AccountLastOrder orders={[{ ...order, status: 'future', status_label: 'Проверяется', created_at: 'invalid', total: Number.NaN }]} isLoading={false} onRetry={vi.fn()} /></MemoryRouter>);
  expect(screen.getByText('Проверяется')).toBeInTheDocument();
  expect(screen.getByText(/Дата уточняется/)).toBeInTheDocument();
  expect(screen.getByText('Сумма уточняется')).toBeInTheDocument();
  expect(screen.queryByText(/NaN|Invalid Date|Оплачен/)).not.toBeInTheDocument();
});

it('renders the same explicit Russian order date and a real zero total on the server', () => {
  const markup = renderToString(<MemoryRouter><AccountLastOrder orders={[{ ...order, total: 0 }]} isLoading={false} onRetry={vi.fn()} /></MemoryRouter>);
  expect(markup).toContain('2 сентября 2026');
  expect(markup).toContain('0 ₽');
});

it('distinguishes first loading, failure and confirmed empty history, and retries the failed request', () => {
  const retry = vi.fn();
  const { rerender } = render(<MemoryRouter><AccountLastOrder orders={[]} isLoading onRetry={retry} /></MemoryRouter>);
  expect(screen.getByRole('status', { name: 'Загрузка последнего заказа' })).toBeInTheDocument();
  expect(screen.queryByText('У вас пока нет заказов')).not.toBeInTheDocument();
  rerender(<MemoryRouter><AccountLastOrder orders={[]} isLoading={false} error={new Error('offline')} onRetry={retry} /></MemoryRouter>);
  expect(screen.getByRole('alert')).toHaveTextContent('Не удалось загрузить последний заказ');
  expect(screen.queryByText('У вас пока нет заказов')).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Повторить' }));
  expect(retry).toHaveBeenCalledTimes(1);
  rerender(<MemoryRouter><AccountLastOrder orders={[]} isLoading={false} onRetry={retry} /></MemoryRouter>);
  expect(screen.getByText('У вас пока нет заказов')).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Перейти в каталог' })).toHaveAttribute('href', '/catalog');
});

it('keeps a known latest cancelled order visible during background loading and failure', () => {
  render(<MemoryRouter><AccountLastOrder orders={[{ ...order, status: 'cancelled' }, { ...order, id: 999, number: 'OLD' }]} isLoading error={new Error('offline')} onRetry={vi.fn()} /></MemoryRouter>);
  expect(screen.getByRole('link', { name: 'Открыть заказ № 48217' })).toHaveAttribute('href', '/orders/42');
  expect(screen.getByText('Отменен')).toBeInTheDocument();
  expect(screen.queryByRole('status')).not.toBeInTheDocument();
  expect(screen.queryByText('Заказ № OLD')).not.toBeInTheDocument();
  expect(screen.getByRole('alert')).toBeInTheDocument();
});
