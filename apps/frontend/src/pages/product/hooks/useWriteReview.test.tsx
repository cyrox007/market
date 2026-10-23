import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { useWriteReview } from './useWriteReview';

const run = (url: string, auth: { isAuthenticated: boolean; isLoading: boolean }) => {
  const wrapper = ({ children }: { children: ReactNode }) => (
    <MemoryRouter initialEntries={[url]}>{children}</MemoryRouter>
  );
  return renderHook((a: typeof auth) => ({ write: useWriteReview(a), location: useLocation() }), {
    wrapper,
    initialProps: auth,
  });
};

describe('useWriteReview', () => {
  it('вошёл — «Написать отзыв» открывает окно', () => {
    const { result } = run('/product/tumba', { isAuthenticated: true, isLoading: false });
    act(() => result.current.write.start());
    expect(result.current.write.open).toBe(true);
    act(() => result.current.write.close());
    expect(result.current.write.open).toBe(false);
  });

  it('гость — на вход с возвратом на товар (вариант в адресе сохраняется)', () => {
    const { result } = run('/product/tumba?variant=3', {
      isAuthenticated: false,
      isLoading: false,
    });
    act(() => result.current.write.start());
    expect(result.current.write.open).toBe(false);
    const { pathname, search } = result.current.location;
    expect(pathname).toBe('/login');
    expect(new URLSearchParams(search).get('next')).toBe('/product/tumba?variant=3&review=1');
  });

  it('вернулся после входа с ?review=1 — окно открыто, флаг из адреса убран', () => {
    const { result, rerender } = run('/product/tumba?variant=3&review=1', {
      isAuthenticated: false,
      isLoading: true,
    });
    expect(result.current.write.open).toBe(false);
    rerender({ isAuthenticated: true, isLoading: false });
    expect(result.current.write.open).toBe(true);
    expect(result.current.location.search).toBe('?variant=3');
  });

  it('?review=1 у гостя — окно не открывается, флаг убран', () => {
    const { result } = run('/product/tumba?review=1', { isAuthenticated: false, isLoading: false });
    expect(result.current.write.open).toBe(false);
    expect(result.current.location.search).toBe('');
  });
});
