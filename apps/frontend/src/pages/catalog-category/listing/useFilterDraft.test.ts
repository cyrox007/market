import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { parseCatalogQuery, type CatalogQuery } from '../../../lib/catalog-query';
import { deferred } from '../../../test/deferred';
import { useFilterDraft, type FilterDraftOptions } from './useFilterDraft';

const applied = parseCatalogQuery('?sort=cheap&colors=grey&page=3');

function renderDraft(options: Partial<FilterDraftOptions> = {}) {
  const countProducts = vi.fn<(q: CatalogQuery) => Promise<number>>(() => Promise.resolve(42));
  const onApply = vi.fn<(next: CatalogQuery) => void>();
  const hook = renderHook(() =>
    useFilterDraft({ applied, appliedTotal: 17, onApply, countProducts, ...options }),
  );
  return { ...hook, countProducts, onApply };
}

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

describe('useFilterDraft', () => {
  it('открытие: черновик — применённые фильтры, число известно без запроса', () => {
    const { result, countProducts } = renderDraft();
    act(() => result.current.open('all'));

    expect(result.current.surface).toBe('all');
    expect(result.current.draft).toEqual(applied);
    expect(result.current.count).toEqual({ value: 17, loading: false });
    act(() => vi.advanceTimersByTime(1000));
    expect(countProducts).not.toHaveBeenCalled();
  });

  it('изменение: число запрашивается через 400 мс после последнего изменения, один раз', async () => {
    const { result, countProducts } = renderDraft();
    act(() => result.current.open('all'));

    act(() => result.current.set({ colors: ['grey', 'red'] }));
    act(() => vi.advanceTimersByTime(200));
    act(() => result.current.set({ onSale: true }));
    expect(result.current.count).toEqual({ value: null, loading: true });

    act(() => vi.advanceTimersByTime(399));
    expect(countProducts).not.toHaveBeenCalled();
    await act(() => vi.advanceTimersByTimeAsync(1));

    expect(countProducts).toHaveBeenCalledTimes(1);
    expect(countProducts.mock.lastCall![0]).toMatchObject({
      colors: ['grey', 'red'],
      onSale: true,
      page: 1,
    });
    expect(result.current.count).toEqual({ value: 42, loading: false });
  });

  it('ответ по прежнему выбору, пришедший позже, не показывается', async () => {
    const slow = deferred<number>();
    const countProducts = vi.fn((q: CatalogQuery) =>
      q.onSale ? Promise.resolve(5) : slow.promise,
    );
    const { result } = renderDraft({ countProducts });
    act(() => result.current.open('all'));

    act(() => result.current.set({ colors: ['red'] }));
    await act(() => vi.advanceTimersByTimeAsync(400));
    act(() => result.current.set({ onSale: true }));
    await act(() => vi.advanceTimersByTimeAsync(400));
    expect(result.current.count).toEqual({ value: 5, loading: false });

    await act(async () => slow.resolve(99));
    expect(result.current.count).toEqual({ value: 5, loading: false });
  });

  it('ошибка подсчёта — без числа и без индикатора, применить можно', async () => {
    const { result } = renderDraft({ countProducts: () => Promise.reject(new Error('500')) });
    act(() => result.current.open('all'));
    act(() => result.current.set({ onSale: true }));
    await act(() => vi.advanceTimersByTimeAsync(400));
    expect(result.current.count).toEqual({ value: null, loading: false });
  });

  it('«Показать N»: применяет черновик с первой страницы и закрывает', () => {
    const { result, onApply } = renderDraft();
    act(() => result.current.open('price'));
    act(() => result.current.set({ price: { min: 10000 } }));
    act(() => result.current.apply());

    expect(onApply).toHaveBeenCalledWith({ ...applied, price: { min: 10000 }, page: 1 });
    expect(result.current.surface).toBeNull();
  });

  it('закрытие без кнопки отменяет черновик', () => {
    const { result, onApply } = renderDraft();
    act(() => result.current.open('all'));
    act(() => result.current.set({ onSale: true }));
    act(() => result.current.discard());

    expect(onApply).not.toHaveBeenCalled();
    expect(result.current.surface).toBeNull();
    act(() => result.current.open('all'));
    expect(result.current.draft).toEqual(applied);
  });

  it('«Сбросить» снимает фильтры в черновике, сортировку оставляет, не применяет', () => {
    const { result, onApply } = renderDraft();
    act(() => result.current.open('all'));
    act(() => result.current.reset());

    expect(result.current.draft).toEqual(parseCatalogQuery('?sort=cheap'));
    expect(onApply).not.toHaveBeenCalled();
  });

  it('число по применённым ещё неизвестно (список грузится) — ждём, а не «Ничего не найдено»', () => {
    const { result, countProducts } = renderDraft({ appliedTotal: null });
    act(() => result.current.open('all'));

    expect(result.current.count).toEqual({ value: null, loading: true });
    act(() => vi.advanceTimersByTime(1000));
    expect(countProducts).not.toHaveBeenCalled();
  });
});
