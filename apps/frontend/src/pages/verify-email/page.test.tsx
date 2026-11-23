import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { StrictMode } from 'react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { api } from '../../lib/api';
import VerifyEmail from './page';

const refreshUser = vi.fn();
vi.mock('../../hooks/useAuth', () => ({ useAuth: () => ({ refreshUser }) }));
vi.mock('../../lib/api', () => ({ api: { accountEmail: { confirm: vi.fn() } } }));
beforeEach(() => {
  vi.clearAllMocks();
  window.history.replaceState(null, '', '/verify-email#token=local-test-token');
  refreshUser.mockResolvedValue(undefined);
});
afterEach(cleanup);

it('consumes the fragment in StrictMode but confirms only after an explicit click', async () => {
  vi.mocked(api.accountEmail.confirm).mockResolvedValue({ status: 'email_changed', message: 'Почта изменена.', requires_login: true });
  render(<MemoryRouter><StrictMode><VerifyEmail /></StrictMode></MemoryRouter>);
  expect(window.location.hash).toBe('');
  expect(api.accountEmail.confirm).not.toHaveBeenCalled();
  fireEvent.click(screen.getByRole('button', { name: 'Подтвердить почту' }));
  expect(await screen.findByRole('status')).toHaveTextContent('Почта изменена.');
  expect(api.accountEmail.confirm).toHaveBeenCalledTimes(1);
});

it('offers a retry after a network error without silently consuming the token', async () => {
  vi.mocked(api.accountEmail.confirm).mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce({ status: 'email_verified', message: 'Почта подтверждена.', requires_login: false });
  render(<MemoryRouter><VerifyEmail /></MemoryRouter>);
  fireEvent.click(screen.getByRole('button', { name: 'Подтвердить почту' }));
  await screen.findByRole('alert');
  fireEvent.click(screen.getByRole('button', { name: 'Подтвердить почту' }));
  expect(await screen.findByRole('status')).toHaveTextContent('Почта подтверждена.');
  expect(api.accountEmail.confirm).toHaveBeenLastCalledWith('local-test-token');
});

it('asks for a new login after changing email and removes the consumed token from the URL', async () => {
  vi.mocked(api.accountEmail.confirm).mockResolvedValue({ status: 'email_changed', message: 'Почта изменена.', requires_login: true });
  render(<MemoryRouter><VerifyEmail /></MemoryRouter>);
  expect(window.location.hash).toBe('');
  fireEvent.click(screen.getByRole('button', { name: 'Подтвердить почту' }));
  expect(await screen.findByText('Почта изменена.')).toBeInTheDocument();
  expect(screen.getByText('Войдите заново с новым адресом электронной почты.')).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Войти' })).toHaveAttribute('href', '/login');
  expect(screen.queryByRole('link', { name: 'Личный кабинет' })).not.toBeInTheDocument();
  expect(api.accountEmail.confirm).toHaveBeenCalledWith('local-test-token');
  expect(refreshUser).toHaveBeenCalledWith(true);
});

it('keeps the account link after verifying the current email without requiring a login', async () => {
  vi.mocked(api.accountEmail.confirm).mockResolvedValue({ status: 'email_verified', message: 'Почта подтверждена.', requires_login: false });
  render(<MemoryRouter><VerifyEmail /></MemoryRouter>);
  fireEvent.click(screen.getByRole('button', { name: 'Подтвердить почту' }));
  expect(await screen.findByText('Почта подтверждена.')).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Личный кабинет' })).toHaveAttribute('href', '/account');
  expect(screen.queryByText('Войдите заново с новым адресом электронной почты.')).not.toBeInTheDocument();
});

it('shows a rejected or expired token without announcing success and permits retry', async () => {
  vi.mocked(api.accountEmail.confirm).mockRejectedValueOnce({ data: { errors: { token: ['Ссылка истекла.'] } } });
  render(<MemoryRouter><VerifyEmail /></MemoryRouter>);
  fireEvent.click(screen.getByRole('button', { name: 'Подтвердить почту' }));
  expect(await screen.findByRole('alert')).toHaveTextContent('Ссылка истекла.');
  expect(screen.queryByRole('status')).not.toBeInTheDocument();
  expect(refreshUser).not.toHaveBeenCalled();
  vi.mocked(api.accountEmail.confirm).mockResolvedValue({ status: 'email_verified', message: 'Подтверждено.', requires_login: false });
  fireEvent.click(screen.getByRole('button', { name: 'Подтвердить почту' }));
  expect(await screen.findByText('Подтверждено.')).toBeInTheDocument();
});
