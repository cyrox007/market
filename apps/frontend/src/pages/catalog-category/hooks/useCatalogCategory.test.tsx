import type { ReactNode } from 'react';
import { renderHook, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Category } from '../../../lib/api';
import { SSRContext } from '../../../contexts/ssr-context';
import { setCategoryFragment } from '../../../lib/category-fragment-cache';
import type { SSRContext as SSRData } from '../../../types/ssr';
import { useCatalogCategory } from './useCatalogCategory';

const api = vi.hoisted(() => ({
  categories: { get: vi.fn() },
  rooms: { get: vi.fn() },
}));
vi.mock('../../../lib/api', () => ({ api }));

const category = (slug: string, name: string) => ({ id: slug.length, slug, name }) as Category;

function wrap(ssr?: SSRData) {
  return ({ children }: { children: ReactNode }) => (
    <SSRContext.Provider value={ssr ? { data: ssr } : undefined}>
      <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
    </SSRContext.Provider>
  );
}

beforeEach(() => {
  api.categories.get.mockReset();
  api.rooms.get.mockReset();
});

describe('раздел по адресу', () => {
  it('из API: пока грузится — «Каталог», потом название раздела', async () => {
    api.categories.get.mockResolvedValue({ category: category('kresla', 'Кресла') });
    const { result } = renderHook(() => useCatalogCategory('kresla', false), { wrapper: wrap() });
    expect(result.current.displayName).toBe('Каталог');
    await waitFor(() => expect(result.current.category?.name).toBe('Кресла'));
    expect(result.current.displayName).toBe('Кресла');
    expect(api.categories.get).toHaveBeenCalledWith('kresla');
  });

  it('комната — из rooms', async () => {
    api.rooms.get.mockResolvedValue({ category: category('gostinaya', 'Гостиная') });
    const { result } = renderHook(() => useCatalogCategory('gostinaya', true), { wrapper: wrap() });
    await waitFor(() => expect(result.current.category?.name).toBe('Гостиная'));
    expect(api.categories.get).not.toHaveBeenCalled();
  });

  it('пришёл с сервера этот же раздел — без запроса', () => {
    const ssr = { category: { category: category('stulya', 'Стулья') } } as SSRData;
    const { result } = renderHook(() => useCatalogCategory('stulya', false), {
      wrapper: wrap(ssr),
    });
    expect(result.current.category?.name).toBe('Стулья');
    expect(api.categories.get).not.toHaveBeenCalled();
  });

  it('с сервера пришёл другой раздел — грузит нужный', async () => {
    api.categories.get.mockResolvedValue({ category: category('tumby', 'Тумбы') });
    const ssr = { category: { category: category('stulya', 'Стулья') } } as SSRData;
    const { result } = renderHook(() => useCatalogCategory('tumby', false), { wrapper: wrap(ssr) });
    await waitFor(() => expect(result.current.category?.name).toBe('Тумбы'));
  });

  it('переход с раздела, пришедшего с сервера, в другой — грузит новый', async () => {
    api.categories.get.mockResolvedValue({ category: category('matrasy', 'Матрасы') });
    const ssr = { category: { category: category('stulya', 'Стулья') } } as SSRData;
    const { result, rerender } = renderHook(({ slug }) => useCatalogCategory(slug, false), {
      wrapper: wrap(ssr),
      initialProps: { slug: 'stulya' },
    });
    rerender({ slug: 'matrasy' });
    await waitFor(() => expect(result.current.category?.name).toBe('Матрасы'));
    expect(api.categories.get).toHaveBeenCalledWith('matrasy');
  });

  it('название из фрагментного кэша — сразу, до ответа', () => {
    setCategoryFragment('komody', { name: 'Комоды', seo: null });
    api.categories.get.mockReturnValue(new Promise(() => {}));
    const { result } = renderHook(() => useCatalogCategory('komody', false), { wrapper: wrap() });
    expect(result.current.displayName).toBe('Комоды');
    expect(result.current.notFound).toBe(false);
  });

  it('API ответил ошибкой — раздел не найден', async () => {
    api.categories.get.mockRejectedValue(new Error('404'));
    const { result } = renderHook(() => useCatalogCategory('net-takogo', false), {
      wrapper: wrap(),
    });
    await waitFor(() => expect(result.current.notFound).toBe(true));
  });
});
