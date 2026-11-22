import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { api } from '../../lib/api';
import { useAccountData } from './useAccountData';
import AccountNotifications from './AccountNotifications';
vi.mock('../../lib/api', () => ({
  api: { auth: { getNotificationSettings: vi.fn(), updateNotificationSettings: vi.fn() } },
}));
const settings = {
  email_promotions: false,
  email_order_notifications: true,
  sms_order_notifications: true,
  push_notifications: true,
  new_product_notifications: false,
};
beforeEach(() => {
  vi.resetAllMocks();
  vi.mocked(api.auth.getNotificationSettings).mockResolvedValue({ settings });
});
afterEach(cleanup);
function Harness() {
  const data = useAccountData(1, 'settings');
  return (
    <AccountNotifications
      settings={data.notificationSettings}
      isLoading={data.isLoadingNotifications}
      error={data.notificationsError}
      onRetry={data.reloadNotifications}
      onSave={data.saveNotifications}
    />
  );
}
function open() {
  return render(
    <SWRConfig value={{ provider: () => new Map(), shouldRetryOnError: false }}>
      <Harness />
    </SWRConfig>,
  );
}
it('saves only one pending intent through SWR while preserving all unrelated server preferences', async () => {
  let finish!: (result: { message: string; settings: typeof settings }) => void;
  vi.mocked(api.auth.updateNotificationSettings).mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        finish = resolve;
      }),
  );
  open();
  const email = await screen.findByRole('checkbox', { name: 'Акции и скидки на email' });
  expect(screen.getAllByRole('checkbox')).toHaveLength(2);
  fireEvent.click(email);
  expect(email).toBeChecked();
  expect(email).toBeDisabled();
  fireEvent.click(screen.getByRole('checkbox', { name: 'Статус заказа на email' }));
  await waitFor(() => expect(api.auth.updateNotificationSettings).toHaveBeenCalledTimes(1));
  expect(api.auth.updateNotificationSettings).toHaveBeenCalledWith({
    ...settings,
    email_promotions: true,
  });
  await act(async () =>
    finish({ message: 'OK', settings: { ...settings, email_promotions: true } }),
  );
  expect(await screen.findByText('Настройки уведомлений сохранены')).toBeInTheDocument();
  expect(screen.queryByRole('checkbox', { name: 'Статус заказа по SMS' })).not.toBeInTheDocument();
  expect(screen.queryByRole('checkbox', { name: 'Push-уведомления' })).not.toBeInTheDocument();
  expect(
    screen.getByRole('checkbox', { name: 'Статус заказа на email' }),
  ).not.toBeDisabled();
  expect(screen.getByRole('checkbox', { name: 'Статус заказа на email' })).toBeChecked();
  expect(screen.getByText('Не чаще одного раза в неделю')).toBeInTheDocument();
  expect(screen.queryByRole('checkbox', { name: 'Уведомления о новинках' })).not.toBeInTheDocument();
});
it('rolls back a failed preference with real SWR and retries only that intent', async () => {
  vi.mocked(api.auth.updateNotificationSettings)
    .mockRejectedValueOnce(new Error('offline'))
    .mockResolvedValueOnce({ message: 'OK', settings: { ...settings, email_promotions: true } });
  open();
  const email = await screen.findByRole('checkbox', { name: 'Акции и скидки на email' });
  fireEvent.click(email);
  expect(await screen.findByRole('alert')).toHaveTextContent('Не удалось сохранить настройки');
  expect(email).not.toBeChecked();
  expect(screen.queryByRole('checkbox', { name: 'Push-уведомления' })).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Повторить сохранение' }));
  expect(await screen.findByText('Настройки уведомлений сохранены')).toBeInTheDocument();
  expect(email).toBeChecked();
  expect(api.auth.updateNotificationSettings).toHaveBeenCalledTimes(2);
  expect(api.auth.updateNotificationSettings).toHaveBeenLastCalledWith({
    ...settings,
    email_promotions: true,
  });
});
it('saves email order notifications without changing marketing or legacy channel preferences', async () => {
  vi.mocked(api.auth.updateNotificationSettings).mockResolvedValue({
    message: 'OK', settings: { ...settings, email_order_notifications: false },
  });
  open();
  fireEvent.click(await screen.findByRole('checkbox', { name: 'Статус заказа на email' }));
  expect(await screen.findByText('Настройки уведомлений сохранены')).toBeInTheDocument();
  expect(screen.getByRole('checkbox', { name: 'Статус заказа на email' })).not.toBeChecked();
  expect(screen.getByRole('checkbox', { name: 'Акции и скидки на email' })).not.toBeChecked();
  expect(api.auth.updateNotificationSettings).toHaveBeenCalledExactlyOnceWith({
    ...settings, email_order_notifications: false,
  });
});
it('shows loading and failed reads without inventing unchecked preferences, then retries', async () => {
  vi.mocked(api.auth.getNotificationSettings)
    .mockRejectedValueOnce(new Error('offline'))
    .mockResolvedValueOnce({ settings });
  open();
  expect(screen.getByRole('status', { name: 'Загрузка настроек уведомлений' })).toBeInTheDocument();
  expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
  expect(await screen.findByRole('alert')).toHaveTextContent(
    'Не удалось загрузить настройки уведомлений',
  );
  expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Повторить' }));
  expect(await screen.findByRole('checkbox', { name: 'Статус заказа на email' })).toBeChecked();
  expect(api.auth.getNotificationSettings).toHaveBeenCalledTimes(2);
});

