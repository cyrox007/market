import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, expect, it, vi } from 'vitest';
import AccountNavigation from './AccountNavigation';

afterEach(cleanup);

it('links to the five sections and counts orders, not ordered units, excluding unknown statuses from active', () => {
  render(<MemoryRouter><AccountNavigation
    orders={{ items: [{ status: 'new' }, { status: 'awaiting_payment' }, { status: 'shipped' }, { status: 'delivered' }, { status: 'cancelled' }, { status: 'future_status' }], isLoading: false }}
    addresses={{ count: 2, isLoading: false }}
    counters={{ wishlistCount: 4, compareCount: 21, isLoading: false }}
    onLogout={vi.fn()} isLoggingOut={false}
  /></MemoryRouter>);
  const nav = screen.getByRole('navigation', { name: 'Разделы кабинета' });
  expect(within(nav).getByRole('link', { name: 'Мои заказы' })).toHaveAttribute('href', '/orders');
  expect(within(nav).getByText('6 заказов, 3 активных')).toBeInTheDocument();
  expect(within(nav).getByText('3 в работе')).toBeInTheDocument();
  expect(within(nav).getByRole('link', { name: 'Избранное' })).toHaveAttribute('href', '/favorites');
  expect(within(nav).getByText('4 товара')).toBeInTheDocument();
  expect(within(nav).getByRole('link', { name: 'Сравнение' })).toHaveAttribute('href', '/compare');
  expect(within(nav).getByText('21 товар')).toBeInTheDocument();
  expect(within(nav).getByRole('link', { name: 'Адреса доставки' })).toHaveAttribute('href', '/account?section=addresses');
  expect(within(nav).getByText('2 адреса')).toBeInTheDocument();
  expect(within(nav).getByRole('link', { name: 'Настройки профиля' })).toHaveAttribute('href', '/account?section=settings');
});

it('retains known quantities while a background refresh is pending or has failed', () => {
  render(<MemoryRouter><AccountNavigation
    orders={{ items: [{ status: 'assembling' }], hasData: true, isLoading: true, error: new Error('offline') }}
    addresses={{ count: 1, hasData: true, isLoading: true, error: new Error('offline') }}
    counters={{ wishlistCount: 21, compareCount: 11, hasData: true, isLoading: true, error: new Error('offline') }}
    onLogout={vi.fn()} isLoggingOut={false} /></MemoryRouter>);
  expect(screen.getByText('1 заказ, 1 активный')).toBeInTheDocument();
  expect(screen.getByText('1 адрес')).toBeInTheDocument();
  expect(screen.getByText('21 товар')).toBeInTheDocument();
  expect(screen.getByText('11 товаров')).toBeInTheDocument();
  expect(screen.queryByRole('status')).not.toBeInTheDocument();
});

it('preserves unrelated query parameters in links to account sections', () => {
  render(<MemoryRouter initialEntries={['/account?from=header']}><AccountNavigation
    orders={{ items: [], isLoading: false }} addresses={{ count: 0, isLoading: false }}
    counters={{ wishlistCount: 0, compareCount: 0, isLoading: false }} onLogout={vi.fn()} isLoggingOut={false} /></MemoryRouter>);
  expect(screen.getByRole('link', { name: 'Адреса доставки' })).toHaveAttribute('href', '/account?from=header&section=addresses');
  expect(screen.getByRole('link', { name: 'Настройки профиля' })).toHaveAttribute('href', '/account?from=header&section=settings');
});

it('distinguishes loading and errors from confirmed zero counts and keeps logout available', () => {
  const logout = vi.fn();
  const props = { orders: { items: [], isLoading: true }, addresses: { count: 0, isLoading: true },
    counters: { wishlistCount: 0, compareCount: 0, isLoading: true }, onLogout: logout, isLoggingOut: false };
  const { rerender } = render(<MemoryRouter><AccountNavigation {...props} /></MemoryRouter>);
  expect(screen.getAllByRole('status', { name: 'Загрузка количества' })).toHaveLength(4);
  expect(screen.queryByText('0 товаров')).not.toBeInTheDocument();
  rerender(<MemoryRouter><AccountNavigation {...props}
    orders={{ items: [], isLoading: false, error: new Error('offline') }}
    addresses={{ count: 0, isLoading: false, error: new Error('offline') }}
    counters={{ wishlistCount: 0, compareCount: 0, isLoading: false, error: new Error('offline') }} /></MemoryRouter>);
  expect(screen.getAllByText('Не удалось загрузить')).toHaveLength(4);
  expect(screen.queryByText('0 адресов')).not.toBeInTheDocument();
  rerender(<MemoryRouter><AccountNavigation {...props} orders={{ items: [], isLoading: false }}
    addresses={{ count: 0, isLoading: false }} counters={{ wishlistCount: 0, compareCount: 0, isLoading: false }} /></MemoryRouter>);
  expect(screen.getByText('0 заказов, 0 активных')).toBeInTheDocument();
  expect(screen.queryByText('0 в работе')).not.toBeInTheDocument();
  expect(screen.getAllByText('0 товаров')).toHaveLength(2);
  expect(screen.getByRole('link', { name: 'Бонусы' })).toHaveAttribute('href', '/account?section=bonuses');
  fireEvent.click(screen.getByRole('button', { name: 'Выйти' }));
  expect(logout).toHaveBeenCalledTimes(1);
});
