import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { api } from '../../lib/api';
import AccountPasswordForm from './AccountPasswordForm';
vi.mock('../../lib/api', () => ({ api: { auth: { changePassword: vi.fn() } } }));
beforeEach(() => vi.resetAllMocks());
afterEach(cleanup);
it('validates required current password, server minimum and confirmation before calling the API', () => {
  render(<AccountPasswordForm />);
  fireEvent.click(screen.getByRole('button', { name: 'Изменить пароль' }));
  expect(screen.getByLabelText('Текущий пароль')).toHaveFocus();
  expect(screen.getByText('Введите текущий пароль')).toBeInTheDocument();
  fireEvent.change(screen.getByLabelText('Текущий пароль'), { target: { value: 'old-password' } });
  fireEvent.change(screen.getByLabelText('Новый пароль'), { target: { value: '1234567' } });
  fireEvent.change(screen.getByLabelText('Повторите пароль'), { target: { value: 'different' } });
  fireEvent.click(screen.getByRole('button', { name: 'Изменить пароль' }));
  expect(
    screen.getByText('Минимум 8 символов', { selector: '[id$="-error"]' }),
  ).toBeInTheDocument();
  expect(screen.getByText('Пароли не совпадают')).toBeInTheDocument();
  expect(api.auth.changePassword).not.toHaveBeenCalled();
});
it('preserves passwords on a server rejection, blocks duplicate sends, and clears them only after success', async () => {
  let reject!: (error: unknown) => void;
  vi.mocked(api.auth.changePassword)
    .mockImplementationOnce(
      () =>
        new Promise((_resolve, fail) => {
          reject = fail;
        }),
    )
    .mockResolvedValueOnce({ message: 'OK' });
  render(<AccountPasswordForm />);
  const password = '  new-password-longer-than-twenty  ';
  fireEvent.change(screen.getByLabelText('Текущий пароль'), {
    target: { value: ' current password ' },
  });
  fireEvent.change(screen.getByLabelText('Новый пароль'), { target: { value: password } });
  fireEvent.change(screen.getByLabelText('Повторите пароль'), { target: { value: password } });
  fireEvent.click(screen.getByRole('button', { name: 'Изменить пароль' }));
  fireEvent.submit(screen.getByRole('form', { name: 'Смена пароля' }));
  expect(screen.getByLabelText('Текущий пароль')).toBeDisabled();
  await waitFor(() => expect(api.auth.changePassword).toHaveBeenCalledTimes(1));
  expect(api.auth.changePassword).toHaveBeenCalledWith({
    current_password: ' current password ',
    password,
    password_confirmation: password,
  });
  await act(async () =>
    reject({ data: { errors: { current_password: ['Текущий пароль неверен'] } } }),
  );
  expect(await screen.findByText('Текущий пароль неверен')).toBeInTheDocument();
  expect(screen.getByLabelText('Текущий пароль')).toHaveFocus();
  expect(screen.getByLabelText('Новый пароль')).toHaveValue(password);
  fireEvent.click(screen.getByRole('button', { name: 'Изменить пароль' }));
  expect(await screen.findByRole('status')).toHaveTextContent('Пароль успешно изменён');
  expect(screen.getByLabelText('Текущий пароль')).toHaveValue('');
  expect(screen.getByLabelText('Новый пароль')).toHaveValue('');
  expect(screen.getByLabelText('Повторите пароль')).toHaveValue('');
});
it('reports a network failure without marking valid fields as invalid', async () => {
  vi.mocked(api.auth.changePassword).mockRejectedValue(new Error('offline'));
  render(<AccountPasswordForm />);
  fireEvent.change(screen.getByLabelText('Текущий пароль'), { target: { value: 'old-password' } });
  fireEvent.change(screen.getByLabelText('Новый пароль'), { target: { value: '12345678' } });
  fireEvent.change(screen.getByLabelText('Повторите пароль'), { target: { value: '12345678' } });
  fireEvent.click(screen.getByRole('button', { name: 'Изменить пароль' }));
  expect(await screen.findByRole('alert')).toHaveTextContent(
    'Не удалось изменить пароль. Попробуйте ещё раз.',
  );
  expect(screen.getByLabelText('Новый пароль')).not.toHaveAttribute('aria-invalid', 'true');
  expect(screen.getByLabelText('Новый пароль')).toHaveValue('12345678');
});
