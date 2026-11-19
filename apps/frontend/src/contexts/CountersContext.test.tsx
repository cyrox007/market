import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { CountersProvider } from './CountersContext';
import { useCounters } from '../hooks/useCounters';
import { api } from '../lib/api';

vi.mock('../lib/api', () => ({ api: { counters: { all: vi.fn() } } }));
afterEach(cleanup);
beforeEach(() => vi.resetAllMocks());

function Counts() {
  const counters = useCounters();
  return <>{counters.isLoading ? <p>Загрузка</p> : counters.error ? <p>Ошибка счетчиков</p> : <p>Избранное: {counters.wishlistCount}</p>}
    <button onClick={() => { void counters.refreshAllCounts(); }}>Повторить</button></>;
}

it('exposes a failed counters request and allows an immediate successful retry', async () => {
  vi.mocked(api.counters.all).mockRejectedValueOnce(new Error('offline'))
    .mockResolvedValueOnce({ cart: 0, wishlist: 4, compare: 2 });
  render(<CountersProvider><Counts /></CountersProvider>);
  expect(await screen.findByText('Ошибка счетчиков')).toBeInTheDocument();
  expect(screen.queryByText('Избранное: 0')).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Повторить' }));
  expect(await screen.findByText('Избранное: 4')).toBeInTheDocument();
});

it('uses server-provided counters immediately without an extra initial request', () => {
  render(<CountersProvider initialCounters={{ cartCount: 1, wishlistCount: 3, compareCount: 2 }}><Counts /></CountersProvider>);
  expect(screen.getByText('Избранное: 3')).toBeInTheDocument();
  expect(api.counters.all).not.toHaveBeenCalled();
});

it('keeps known counts visible during background refresh', async () => {
  let finish!: (value: { cart: number; wishlist: number; compare: number }) => void;
  vi.mocked(api.counters.all).mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
  render(<CountersProvider initialCounters={{ cartCount: 0, wishlistCount: 4, compareCount: 2 }}><Counts /></CountersProvider>);
  fireEvent.click(screen.getByRole('button', { name: 'Повторить' }));
  await waitFor(() => expect(api.counters.all).toHaveBeenCalledTimes(1));
  const showedLoading = screen.queryByText('Загрузка') !== null;
  await act(async () => { finish({ cart: 0, wishlist: 5, compare: 2 }); });
  expect(showedLoading).toBe(false);
  expect(screen.getByText('Избранное: 5')).toBeInTheDocument();
});
