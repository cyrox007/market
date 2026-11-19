import { act, cleanup, render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, expect, it, vi } from 'vitest';
import OrderDetail from './page';
import { api } from '../../../lib/api';

vi.mock('../../../lib/api', () => ({ api: { orders: { get: vi.fn() } } }));
vi.mock('../../../hooks/useAuth', () => ({ useAuth: () => ({ user: null, updateProfile: vi.fn() }) }));
vi.mock('../../../contexts/cart-panel-context', () => ({ useCartPanel: () => ({ openCart: vi.fn() }) }));
vi.mock('../../../components/orders/OrderStatusBadge', () => ({ default: () => null }));
vi.mock('../../../components/orders/OrderTracking', () => ({ default: () => null }));
vi.mock('../../../components/orders/OrderItemsList', () => ({ default: () => null }));

afterEach(() => {
  cleanup();
  vi.useRealTimers();
  vi.clearAllMocks();
  sessionStorage.clear();
});

it.each([0, 2 * 60 * 60 * 1000, null])('keeps counting down across repeated polls (client clock skew: %s)', async (skew) => {
  vi.useFakeTimers();
  const created = Date.parse('2026-11-19T10:00:00Z');
  vi.setSystemTime(created + (skew ?? 0));
  const deadline = new Date(created + 60 * 60 * 1000).toISOString();
  vi.mocked(api.orders.get).mockImplementation(async () => ({ order: {
    id: 1, number: 'TEST-1', status: 'manager_processing', created_at: new Date(created).toISOString(),
    cooling_until: deadline,
    server_time: skew === null ? undefined : new Date(Date.now() - skew).toISOString(),
    subtotal: 1000, total: 1000, delivery_cost: 0, assembly_cost: 0,
    items: [], additional_services: [], requires_assembly: false,
    contact_name: 'Покупатель', contact_email: 'buyer@example.test', contact_phone: '+79991234567',
    payment_status: 'paid', payment_method: 'card_sberbank', delivery_type: 'pickup',
    can_be_cancelled: true, can_pay: false,
  } } as never));

  await act(async () => {
    render(<MemoryRouter initialEntries={['/orders/1']}>
      <Routes><Route path="/orders/:id" element={<OrderDetail />} /></Routes>
    </MemoryRouter>);
  });
  expect(screen.getByText(/Период охлаждения:/)).toHaveTextContent('60:00');

  await act(async () => { await vi.advanceTimersByTimeAsync(31_000); });
  expect(api.orders.get).toHaveBeenCalledTimes(2);
  expect(screen.getByText(/Период охлаждения:/)).toHaveTextContent('59:29');

  await act(async () => { await vi.advanceTimersByTimeAsync(31_000); });
  expect(api.orders.get).toHaveBeenCalledTimes(3);
  expect(screen.getByText(/Период охлаждения:/)).toHaveTextContent('58:58');
});
