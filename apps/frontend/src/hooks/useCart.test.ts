import { renderHook } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { useCart } from './useCart';

const mutateMock = vi.fn();
const useSWRMock = vi.fn(() => ({
  data: null,
  error: null,
  isLoading: false,
  mutate: mutateMock,
}));

vi.mock('swr', () => ({
  default: (...args: any[]) => useSWRMock(...args),
}));

vi.mock('./useCounters', () => ({
  useCounters: () => ({
    refreshCartCount: vi.fn(),
  }),
}));

vi.mock('./useRegion', () => ({
  useRegion: () => ({
    region: { id: 15 },
    getRegionId: () => 15,
  }),
}));

vi.mock('../lib/api', () => ({
  api: {
    cart: {
      get: vi.fn(async () => ({ items: [], subtotal: 0, item_count: 0, is_empty: true })),
      add: vi.fn(),
      update: vi.fn(),
      remove: vi.fn(),
      clear: vi.fn(),
      count: vi.fn(async () => ({ count: 0 })),
    },
  },
}));

describe('useCart', () => {
  it('uses region-aware SWR key', () => {
    renderHook(() => useCart());

    expect(useSWRMock).toHaveBeenCalled();
    expect(useSWRMock.mock.calls[0][0]).toBe('/api/cart?region_id=15');
  });
});
