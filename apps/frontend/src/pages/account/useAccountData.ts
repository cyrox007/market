import useSWR from 'swr';
import { api, type NotificationSettings } from '../../lib/api';
import { runSessionRead, runSessionTask } from '../../lib/session-queue';
import type { AccountSection } from './useAccountSection';

const options = { keepPreviousData: false, shouldRetryOnError: false };
const readAddresses = () => runSessionRead(async () => {
  const { data } = await api.addresses.list();
  return [...data].sort((a, b) => Number(b.is_default) - Number(a.is_default));
});

/** The overview and order list share one owner-scoped cache and session read. */
export function useAccountOrders(userId: number | undefined, enabled = true) {
  return useSWR(
    userId && enabled ? ['account', userId, 'orders'] : null,
    () => runSessionRead(async () => (await api.orders.list()).data), options,
  );
}

/** Shared account reads. Keys include the owner; data never carries over to another user. */
export function useAccountData(userId: number | undefined, section: AccountSection) {
  const overview = section === 'profile';
  const orders = useAccountOrders(userId, overview);
  const addresses = useSWR(
    userId && (overview || section === 'addresses') ? ['account', userId, 'addresses'] : null,
    readAddresses, options,
  );
  const notifications = useSWR(
    userId && (overview || section === 'settings') ? ['account', userId, 'notifications'] : null,
    () => runSessionRead(async () => (await api.auth.getNotificationSettings()).settings), options,
  );

  const saveNotifications = async (patch: Partial<NotificationSettings>) => {
    const loaded = notifications.data;
    if (!loaded) throw new Error('Настройки ещё не загружены');
    return notifications.mutate(
      async (current) => {
        if (!current) throw new Error('Настройки ещё не загружены');
        const response = await runSessionTask(() => api.auth.updateNotificationSettings({ ...current, ...patch }));
        return response.settings;
      },
      {
        optimisticData: (current) => ({ ...(current ?? loaded), ...patch }),
        rollbackOnError: true,
        revalidate: false,
        throwOnError: true,
      },
    );
  };

  return {
    orders: orders.data ?? [],
    hasOrders: orders.data !== undefined,
    isLoadingOrders: orders.isLoading,
    ordersError: orders.error,
    reloadOrders: orders.mutate,
    addresses: addresses.data ?? [],
    hasAddresses: addresses.data !== undefined,
    isLoadingAddresses: addresses.isLoading,
    addressesError: addresses.error,
    reloadAddresses: () => addresses.mutate(readAddresses, { revalidate: false, throwOnError: true }),
    notificationSettings: notifications.data,
    isLoadingNotifications: notifications.isLoading,
    notificationsError: notifications.error,
    reloadNotifications: notifications.mutate,
    saveNotifications,
  };
}
