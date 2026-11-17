import type { ReactNode } from 'react';
import { act, cleanup, renderHook, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { api } from '../../lib/api';
import { useAccountData } from './useAccountData';

vi.mock('../../lib/api', () => ({ api: {
  orders: { list: vi.fn() }, addresses: { list: vi.fn() },
  auth: { getNotificationSettings: vi.fn(), updateNotificationSettings: vi.fn() },
} }));

const settings = { email_promotions: false, sms_order_notifications: true, push_notifications: false, new_product_notifications: true };
function wrapper() {
  const cache = new Map();
  return function Wrapper({ children }: { children: ReactNode }) {
    return <SWRConfig value={{ provider: () => cache, dedupingInterval: 0, shouldRetryOnError: false }}>{children}</SWRConfig>;
  };
}
beforeEach(() => {
  vi.resetAllMocks();
  vi.mocked(api.orders.list).mockResolvedValue({ data: [] });
  vi.mocked(api.addresses.list).mockResolvedValue({ data: [] });
  vi.mocked(api.auth.getNotificationSettings).mockResolvedValue({ settings });
});
afterEach(cleanup);

it('loads overview data without inventing notification defaults and puts the default address first', async () => {
  vi.mocked(api.addresses.list).mockResolvedValue({ data: [
    { id: 1, title: 'Дача', is_default: false },
    { id: 2, title: 'Дом', is_default: true },
  ] } as never);
  const { result } = renderHook(() => useAccountData(1, 'profile'), { wrapper: wrapper() });
  expect(result.current.notificationSettings).toBeUndefined();
  expect(result.current.isLoadingOrders).toBe(true);
  await waitFor(() => expect(result.current.notificationSettings).toEqual(settings));
  expect(result.current.addresses.map((address) => address.id)).toEqual([2, 1]);
  expect(result.current.orders).toEqual([]);
  expect(result.current.ordersError).toBeUndefined();
});

it('distinguishes a failed orders request from an empty history and allows retrying only that request', async () => {
  vi.mocked(api.orders.list).mockRejectedValueOnce(new Error('offline'));
  const { result } = renderHook(() => useAccountData(1, 'profile'), { wrapper: wrapper() });
  await waitFor(() => expect(result.current.ordersError?.message).toBe('offline'));
  expect(result.current.notificationSettings).toEqual(settings);
  expect(result.current.addressesError).toBeUndefined();
  await act(async () => { await result.current.reloadOrders(); });
  await waitFor(() => expect(result.current.ordersError).toBeUndefined());
  expect(result.current.orders).toEqual([]);
  expect(api.auth.getNotificationSettings).toHaveBeenCalledTimes(1);
});

it('does not show one buyer’s addresses after switching accounts or signing out', async () => {
  let finishSecondRequest!: (value: { data: [] }) => void;
  vi.mocked(api.addresses.list).mockResolvedValueOnce({ data: [{ id: 10, title: 'Личный адрес' }] } as never)
    .mockImplementationOnce(() => new Promise((resolve) => { finishSecondRequest = resolve; }));
  const { result, rerender } = renderHook(({ id }: { id: number | undefined }) => useAccountData(id, 'profile'), {
    initialProps: { id: 1 as number | undefined }, wrapper: wrapper(),
  });
  await waitFor(() => expect(result.current.addresses[0]?.id).toBe(10));
  rerender({ id: 2 });
  expect(result.current.addresses).toEqual([]);
  expect(result.current.notificationSettings).toBeUndefined();
  await waitFor(() => expect(api.addresses.list).toHaveBeenCalledTimes(2));
  rerender({ id: undefined });
  expect(result.current.orders).toEqual([]);
  expect(result.current.addresses).toEqual([]);
  await act(async () => { finishSecondRequest({ data: [] }); });
});

it('rolls back a failed notification change and preserves unrelated preferences', async () => {
  vi.mocked(api.auth.updateNotificationSettings).mockRejectedValueOnce(new Error('save failed'));
  const { result } = renderHook(() => useAccountData(1, 'settings'), { wrapper: wrapper() });
  await waitFor(() => expect(result.current.notificationSettings).toEqual(settings));
  await act(async () => {
    await expect(result.current.saveNotifications({ email_promotions: true })).rejects.toThrow('save failed');
  });
  expect(result.current.notificationSettings).toEqual(settings);
  expect(api.auth.updateNotificationSettings).toHaveBeenCalledWith({ ...settings, email_promotions: true });
});

it('shares saved notification preferences with the overview', async () => {
  vi.mocked(api.auth.updateNotificationSettings).mockResolvedValue({ message: 'OK', settings: { ...settings, email_promotions: true } });
  const { result, rerender } = renderHook(({ section }: { section: 'settings' | 'profile' }) => useAccountData(1, section), {
    initialProps: { section: 'settings' as 'settings' | 'profile' }, wrapper: wrapper(),
  });
  await waitFor(() => expect(result.current.notificationSettings).toEqual(settings));
  await act(async () => { await result.current.saveNotifications({ email_promotions: true }); });
  rerender({ section: 'profile' });
  await waitFor(() => {
    expect(result.current.isLoadingOrders).toBe(false);
    expect(result.current.isLoadingAddresses).toBe(false);
  });
  expect(result.current.notificationSettings?.email_promotions).toBe(true);
  expect(result.current.notificationSettings?.sms_order_notifications).toBe(true);
});

it('does not fetch private data for a guest or unrelated resources in the address section', async () => {
  const { result, rerender } = renderHook(({ id }: { id: number | undefined }) => useAccountData(id, 'addresses'), {
    initialProps: { id: undefined as number | undefined }, wrapper: wrapper(),
  });
  expect(api.addresses.list).not.toHaveBeenCalled();
  rerender({ id: 1 });
  await waitFor(() => expect(result.current.isLoadingAddresses).toBe(false));
  expect(api.addresses.list).toHaveBeenCalledTimes(1);
  expect(api.orders.list).not.toHaveBeenCalled();
  expect(api.auth.getNotificationSettings).not.toHaveBeenCalled();
});

it('ignores a late address response from a previous account after the next buyer has loaded', async () => {
  let finishFirstRequest!: (value: { data: { id: number; title: string; is_default: boolean }[] }) => void;
  vi.mocked(api.addresses.list)
    .mockImplementationOnce(() => new Promise((resolve) => { finishFirstRequest = resolve; }))
    .mockResolvedValueOnce({ data: [{ id: 20, title: 'Адрес второго покупателя' }] } as never);
  const { result, rerender } = renderHook(({ id }) => useAccountData(id, 'addresses'), {
    initialProps: { id: 1 }, wrapper: wrapper(),
  });
  await waitFor(() => expect(api.addresses.list).toHaveBeenCalledTimes(1));
  rerender({ id: 2 });
  await waitFor(() => expect(result.current.addresses[0]?.id).toBe(20));
  await act(async () => { finishFirstRequest({ data: [{ id: 10, title: 'Адрес первого покупателя', is_default: true }] }); });
  expect(result.current.addresses.map((address) => address.id)).toEqual([20]);
});
