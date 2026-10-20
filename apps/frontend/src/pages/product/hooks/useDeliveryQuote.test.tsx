import type { ReactNode } from 'react';
import { renderHook, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ProductDetail, ProductVariant } from '../../../lib/api';
import { useDeliveryQuote } from './useDeliveryQuote';

const quote = vi.hoisted(() => vi.fn());
const region = vi.hoisted(() => ({
  current: {
    locality: { externalId: 'lipetsk', name: 'Липецк' } as {
      externalId: string;
      name: string;
    } | null,
    regionId: 9 as number | null,
  },
}));

vi.mock('../../../lib/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../../lib/api')>();
  return { ...actual, api: { ...actual.api, shipping: { ...actual.api.shipping, quote } } };
});

vi.mock('../../../hooks/useRegion', () => ({
  useRegion: () => ({
    locality: region.current.locality,
    region: null,
    getRegionId: () => region.current.regionId,
  }),
}));

const range = (min: number, max: number) => ({ days_min: min, days_max: max });

const parent = { id: 27, is_variable: true, is_variant: false } as unknown as ProductDetail;
const simple = { id: 5, is_variable: false, is_variant: false } as unknown as ProductDetail;
const variant = (id: number) => ({ id }) as unknown as ProductVariant;

const wrapper = ({ children }: { children: ReactNode }) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
);

beforeEach(() => {
  quote.mockReset();
  region.current = { locality: { externalId: 'lipetsk', name: 'Липецк' }, regionId: 9 };
  quote.mockResolvedValue({
    available: true,
    selected: null,
    options: [{ delivery: range(3, 5) }],
    pickup: [{ ready: { ...range(0, 0), label: 'Сегодня' } }],
  });
});

describe('сроки доставки на странице товара', () => {
  it('считает по варианту, количеству и населённому пункту покупателя', async () => {
    const { result } = renderHook(() => useDeliveryQuote(parent, variant(28), 2), { wrapper });
    await waitFor(() => expect(result.current.delivery).toBe('3–5 дней'));
    expect(result.current.pickup).toBe('Сегодня');
    expect(quote).toHaveBeenCalledWith({
      directory_external_id: 'lipetsk',
      region_id: 9,
      items: [{ product_id: 27, variant_id: 28, quantity: 2 }],
    });
  });

  it('у простого товара — без variant_id; в корзине 0 — считаем за 1', async () => {
    renderHook(() => useDeliveryQuote(simple, null, 0), { wrapper });
    await waitFor(() =>
      expect(quote).toHaveBeenCalledWith(
        expect.objectContaining({ items: [{ product_id: 5, quantity: 1 }] }),
      ),
    );
  });

  it('смена варианта или количества — новый расчёт', async () => {
    const { rerender } = renderHook(
      ({ v, q }: { v: number; q: number }) => useDeliveryQuote(parent, variant(v), q),
      { wrapper, initialProps: { v: 28, q: 1 } },
    );
    await waitFor(() => expect(quote).toHaveBeenCalledTimes(1));
    rerender({ v: 31, q: 1 });
    await waitFor(() => expect(quote).toHaveBeenCalledTimes(2));
    rerender({ v: 31, q: 3 });
    await waitFor(() => expect(quote).toHaveBeenCalledTimes(3));
    expect(quote).toHaveBeenLastCalledWith(
      expect.objectContaining({ items: [{ product_id: 27, variant_id: 31, quantity: 3 }] }),
    );
  });

  it('смена населённого пункта — новый расчёт', async () => {
    const { rerender } = renderHook(() => useDeliveryQuote(parent, variant(28), 1), { wrapper });
    await waitFor(() => expect(quote).toHaveBeenCalledTimes(1));
    region.current = { locality: { externalId: 'voronezh', name: 'Воронеж' }, regionId: 4 };
    rerender();
    await waitFor(() =>
      expect(quote).toHaveBeenLastCalledWith(
        expect.objectContaining({ directory_external_id: 'voronezh', region_id: 4 }),
      ),
    );
  });

  it('населённый пункт не выбран — не считаем и строк нет', () => {
    region.current = { locality: null, regionId: null };
    const { result } = renderHook(() => useDeliveryQuote(parent, variant(28), 1), { wrapper });
    expect(quote).not.toHaveBeenCalled();
    expect(result.current).toEqual({ delivery: null, pickup: null });
  });

  it('расчёт упал — строк нет, без ошибки', async () => {
    quote.mockRejectedValue(new Error('503'));
    const { result } = renderHook(() => useDeliveryQuote(parent, variant(28), 1), { wrapper });
    await waitFor(() => expect(quote).toHaveBeenCalled());
    expect(result.current).toEqual({ delivery: null, pickup: null });
  });
});
