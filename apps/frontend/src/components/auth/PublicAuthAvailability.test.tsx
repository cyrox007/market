import { cleanup, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Login from '../../pages/login/page';
import Register from '../../pages/register/page';

const state = vi.hoisted(() => ({
  ready: true, registrationEnabled: true, loginEnabled: true,
  error: null as Error | null, reload: vi.fn(),
}));
vi.mock('../../hooks/usePublicAuthSettings', () => ({ usePublicAuthSettings: () => state }));
vi.mock('../../hooks/useAuth', () => ({ useAuth: () => ({ isAuthenticated: false, login: vi.fn(), register: vi.fn() }) }));
vi.mock('../../hooks/usePageSeo', () => ({ usePageSeo: () => {} }));
vi.mock('../ui/PhoneInput', () => ({ default: () => null }));
beforeEach(() => { state.ready = true; state.registrationEnabled = true; state.loginEnabled = true; state.error = null; });
afterEach(cleanup);

it('disabled login does not show the login form', () => {
  state.loginEnabled = false;
  render(<MemoryRouter><Login /></MemoryRouter>);
  expect(screen.getByRole('status')).toHaveTextContent('Вход на сайт временно отключён');
  expect(screen.queryByLabelText('Email *')).not.toBeInTheDocument();
});

it('disabled registration does not show registration form', () => {
  state.registrationEnabled = false;
  render(<MemoryRouter><Register /></MemoryRouter>);
  expect(screen.getByRole('status')).toHaveTextContent('Регистрация временно отключена');
  expect(screen.queryByRole('button', { name: 'Зарегистрироваться' })).not.toBeInTheDocument();
});

it('login remains available when only registration is disabled', () => {
  state.registrationEnabled = false;
  render(<MemoryRouter><Login /></MemoryRouter>);
  expect(screen.getByRole('button', { name: 'Войти' })).toBeInTheDocument();
  expect(screen.queryByRole('link', { name: 'Зарегистрироваться' })).not.toBeInTheDocument();
});

it('settings failure shows retry instead of silently enabling registration', () => {
  state.error = new Error('unavailable');
  render(<MemoryRouter><Register /></MemoryRouter>);
  expect(screen.getByRole('button', { name: 'Повторить' })).toBeInTheDocument();
  expect(screen.queryByRole('button', { name: 'Зарегистрироваться' })).not.toBeInTheDocument();
});

it('loading settings does not flash an enabled login form', () => {
  state.ready = false;
  render(<MemoryRouter><Login /></MemoryRouter>);
  expect(screen.getByRole('status')).toHaveTextContent('Проверяем');
  expect(screen.queryByRole('button', { name: 'Войти' })).not.toBeInTheDocument();
});
