import { cleanup, render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { afterEach, expect, it, vi } from 'vitest';
import ProtectedRoute from './ProtectedRoute';

vi.mock('../../hooks/useAuth', () => ({ useAuth: () => ({ isAuthenticated: false, isLoading: false }) }));
afterEach(cleanup);

function LoginDestination() {
  const location = useLocation();
  return <output>{new URLSearchParams(location.search).get('next') ?? 'Нет адреса возврата'}</output>;
}

it('preserves a private account section and hash when sending a guest to login', () => {
  render(<MemoryRouter initialEntries={['/account?section=addresses#saved']}><Routes>
    <Route path="/account" element={<ProtectedRoute><div>Кабинет</div></ProtectedRoute>} />
    <Route path="/login" element={<LoginDestination />} />
  </Routes></MemoryRouter>);
  expect(screen.getByText('/account?section=addresses#saved')).toBeInTheDocument();
});
