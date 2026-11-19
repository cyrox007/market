import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import AccountEmailSettings from './AccountEmailSettings';
import { api } from '../../lib/api';
import { resetSessionQueue, runSessionTask } from '../../lib/session-queue';

vi.mock('../../lib/api', () => ({
  api: {
    accountEmail: {
      status: vi.fn(),
      verify: vi.fn(),
      change: vi.fn(),
      cancel: vi.fn(),
    },
  },
}));
const initial = {
  email: 'old@example.test',
  is_verified: false,
  email_verified_at: null,
  pending_email: null,
  pending_expires_at: null,
};
beforeEach(() => {
  vi.clearAllMocks();
  resetSessionQueue();
  vi.mocked(api.accountEmail.status).mockResolvedValue(initial);
  vi.mocked(api.accountEmail.verify).mockResolvedValue({
    status: 'confirmation_sent',
    message: 'Письмо отправлено.',
  });
});
afterEach(cleanup);

it('queues reads behind session mutations and avoids sending a successful action twice after a refresh error', async () => {
  let release!: () => void;
  const pending = runSessionTask(() => new Promise<void>(resolve => { release = resolve; }));
  render(<AccountEmailSettings userId={1} email={initial.email} />);
  await act(async () => { await Promise.resolve(); });
  expect(api.accountEmail.status).not.toHaveBeenCalled();
  await act(async () => { release(); await pending; });
  await screen.findByRole('button', { name: 'Подтвердить почту' });
  vi.mocked(api.accountEmail.status).mockRejectedValueOnce(new Error('offline'));
  fireEvent.click(screen.getByRole('button', { name: 'Подтвердить почту' }));
  expect(await screen.findByRole('alert')).toHaveTextContent('Действие выполнено');
  expect(api.accountEmail.verify).toHaveBeenCalledTimes(1);
  fireEvent.click(screen.getByRole('button', { name: 'Повторить загрузку' }));
  await screen.findByRole('button', { name: 'Подтвердить почту' });
  expect(api.accountEmail.verify).toHaveBeenCalledTimes(1);
});

it('shows verification status and requests a confirmation email', async () => {
  render(<AccountEmailSettings userId={1} email={initial.email} />);
  fireEvent.click(await screen.findByRole('button', { name: 'Подтвердить почту' }));
  expect(await screen.findByRole('status')).toHaveTextContent('Письмо отправлено.');
  expect(api.accountEmail.verify).toHaveBeenCalledTimes(1);
});

it('keeps the existing email while a password-protected change awaits confirmation', async () => {
  vi.mocked(api.accountEmail.change).mockResolvedValue({
    status: 'confirmation_sent',
    message: 'Проверьте новую почту.',
  });
  render(<AccountEmailSettings userId={1} email={initial.email} />);
  fireEvent.click(await screen.findByRole('button', { name: 'Изменить почту' }));
  fireEvent.change(screen.getByLabelText('Новая почта'), { target: { value: 'new@example.test' } });
  fireEvent.change(screen.getByLabelText('Текущий пароль'), {
    target: { value: 'secret-password' },
  });
  vi.mocked(api.accountEmail.status).mockResolvedValue({
    ...initial,
    pending_email: 'new@example.test',
  });
  fireEvent.click(screen.getByRole('button', { name: 'Отправить подтверждение' }));
  await waitFor(() =>
    expect(api.accountEmail.change).toHaveBeenCalledWith('new@example.test', 'secret-password'),
  );
  expect(await screen.findByText('new@example.test')).toBeInTheDocument();
  expect(screen.getByText('old@example.test')).toBeInTheDocument();
  expect(screen.queryByLabelText('Текущий пароль')).not.toBeInTheDocument();
});

it('displays validation errors without changing the account email', async () => {
  vi.mocked(api.accountEmail.change).mockRejectedValue({
    data: { errors: { current_password: ['Текущий пароль неверен.'] } },
  });
  render(<AccountEmailSettings userId={1} email={initial.email} />);
  fireEvent.click(await screen.findByRole('button', { name: 'Изменить почту' }));
  fireEvent.change(screen.getByLabelText('Новая почта'), { target: { value: 'new@example.test' } });
  fireEvent.change(screen.getByLabelText('Текущий пароль'), { target: { value: 'wrong' } });
  fireEvent.click(screen.getByRole('button', { name: 'Отправить подтверждение' }));
  expect(await screen.findByRole('alert')).toHaveTextContent('Текущий пароль неверен.');
  expect(screen.getByText('old@example.test')).toBeInTheDocument();
});
