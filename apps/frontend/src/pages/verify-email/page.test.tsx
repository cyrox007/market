import { StrictMode } from 'react';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, expect, it, vi } from 'vitest';
import VerifyEmail from './page';
import { api } from '../../lib/api';

const { refreshUser } = vi.hoisted(() => ({ refreshUser: vi.fn() }));
vi.mock('../../hooks/useAuth', () => ({ useAuth: () => ({ refreshUser }) }));
vi.mock('../../lib/api', () => ({ api: { accountEmail: { confirm: vi.fn() } } }));
afterEach(() => {
  cleanup();
  vi.clearAllMocks();
  window.history.replaceState(null, '', '/');
});

it('consumes the fragment in StrictMode but confirms only after an explicit click', async () => {
  window.history.replaceState(null, '', '/verify-email#token=abc123');
  vi.mocked(api.accountEmail.confirm).mockResolvedValue({
    status: 'email_changed',
    message: 'Почта изменена. Войдите с новым адресом.',
    requires_login: true,
  });
  render(
    <MemoryRouter>
      <StrictMode>
        <VerifyEmail />
      </StrictMode>
    </MemoryRouter>,
  );
  const button = await screen.findByRole('button', { name: 'Подтвердить почту' });
  expect(window.location.hash).toBe('');
  expect(api.accountEmail.confirm).not.toHaveBeenCalled();
  fireEvent.click(button);
  await waitFor(() => expect(api.accountEmail.confirm).toHaveBeenCalledTimes(1));
  expect(api.accountEmail.confirm).toHaveBeenCalledWith('abc123');
  expect(await screen.findByRole('status')).toHaveTextContent('Почта изменена.');
  expect(refreshUser).toHaveBeenCalledWith(true);
});

it('offers a retry after a network error without silently consuming the token', async () => {
  window.history.replaceState(null, '', '/verify-email#token=retry');
  vi.mocked(api.accountEmail.confirm)
    .mockRejectedValueOnce(new Error('offline'))
    .mockResolvedValueOnce({
      status: 'email_verified',
      message: 'Почта подтверждена.',
      requires_login: false,
    });
  render(
    <MemoryRouter>
      <VerifyEmail />
    </MemoryRouter>,
  );
  fireEvent.click(await screen.findByRole('button', { name: 'Подтвердить почту' }));
  await screen.findByRole('alert');
  fireEvent.click(screen.getByRole('button', { name: 'Подтвердить почту' }));
  expect(await screen.findByRole('status')).toHaveTextContent('Почта подтверждена.');
});
