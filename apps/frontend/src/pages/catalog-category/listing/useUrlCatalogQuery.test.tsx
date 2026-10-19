import type { ReactNode } from 'react';
import { act, renderHook } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { parseCatalogQuery } from '../../../lib/catalog-query';
import { useUrlCatalogQuery } from './useUrlCatalogQuery';

const wrapper = ({ children }: { children: ReactNode }) => (
  <BrowserRouter>{children}</BrowserRouter>
);

describe('запрос каталога в адресе', () => {
  it('применили из открытой шторки — адрес заменяет её запись: «Назад» не ведёт на прежний выбор дважды', () => {
    window.history.replaceState(null, '', '/catalog/divany');
    const { result } = renderHook(() => useUrlCatalogQuery(), { wrapper });
    // Шторка положила свою запись поверх страницы
    window.history.pushState({ ...window.history.state, sheet: true }, '');
    const length = window.history.length;

    act(() => result.current[1](parseCatalogQuery('?on_sale=1')));

    expect(window.location.search).toBe('?on_sale=1');
    expect(window.history.length).toBe(length);
  });

  it('без шторки — обычный переход новой записью', () => {
    window.history.replaceState(null, '', '/catalog/divany');
    const { result } = renderHook(() => useUrlCatalogQuery(), { wrapper });
    const length = window.history.length;

    act(() => result.current[1](parseCatalogQuery('?sort=cheap')));

    expect(window.location.search).toBe('?sort=cheap');
    expect(window.history.length).toBe(length + 1);
  });
});
