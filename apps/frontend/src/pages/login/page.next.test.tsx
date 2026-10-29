import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Login from './page';
import Register from '../register/page';

const auth = { isAuthenticated: false, login: vi.fn(), register: vi.fn() };
vi.mock('../../hooks/useAuth', () => ({ useAuth: () => auth }));
vi.mock('../../hooks/usePageSeo', () => ({ usePageSeo: () => {} }));
vi.mock('../../hooks/usePublicAuthSettings', () => ({
  usePublicAuthSettings: () => ({
    ready: true,
    loginEnabled: true,
    registrationEnabled: true,
    error: null,
  }),
}));

function Where() {
  const l = useLocation();
  return <p data-testid="where">{l.pathname + l.search}</p>;
}

const at = (url: string) =>
  render(
    <MemoryRouter initialEntries={[url]}>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="*" element={<Where />} />
      </Routes>
    </MemoryRouter>,
  );

const signIn = () => {
  fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'a@b.ru' } });
  fireEvent.change(screen.getByLabelText(/пароль/i), { target: { value: 'secret12' } });
  fireEvent.submit(screen.getByLabelText(/email/i).closest('form')!);
};

describe('вход и регистрация возвращают по next', () => {
  beforeEach(() => {
    auth.isAuthenticated = false;
    auth.login.mockReset().mockResolvedValue(undefined);
  });

  it('после входа — на товар с открытым окном', async () => {
    at('/login?next=%2Fproduct%2Ftumba%3Freview%3D1');
    signIn();
    await waitFor(() =>
      expect(screen.getByTestId('where')).toHaveTextContent('/product/tumba?review=1'),
    );
  });

  it('чужой адрес в next — в кабинет', async () => {
    at('/login?next=%2F%2Fevil.com');
    signIn();
    await waitFor(() => expect(screen.getByTestId('where')).toHaveTextContent('/account'));
  });

  it('уже вошёл — сразу по next', async () => {
    auth.isAuthenticated = true;
    at('/login?next=%2Fproduct%2Ftumba%3Freview%3D1');
    await waitFor(() =>
      expect(screen.getByTestId('where')).toHaveTextContent('/product/tumba?review=1'),
    );
  });

  it('ссылка «Регистрация» несёт next дальше, и обратно', () => {
    at('/login?next=%2Fproduct%2Ftumba');
    const toRegister = screen
      .getAllByRole('link')
      .find((a) => a.getAttribute('href')?.startsWith('/register'))!;
    expect(toRegister).toHaveAttribute('href', '/register?next=%2Fproduct%2Ftumba');
    fireEvent.click(toRegister);
    const toLogin = screen
      .getAllByRole('link')
      .find((a) => a.getAttribute('href')?.startsWith('/login'))!;
    expect(toLogin).toHaveAttribute('href', '/login?next=%2Fproduct%2Ftumba');
  });
});
