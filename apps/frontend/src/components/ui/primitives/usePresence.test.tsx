import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { usePresence } from './usePresence';

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

const frame = () => act(() => vi.advanceTimersByTime(20));

describe('usePresence', () => {
  it('закрытое — не на странице', () => {
    const { result } = renderHook(() => usePresence(false, 200));
    expect(result.current).toEqual({ rendered: false, shown: false, leaving: false });
  });

  it('открытие: сначала на странице за краем, через кадр — на месте', () => {
    const { result, rerender } = renderHook(({ open }) => usePresence(open, 200), {
      initialProps: { open: false },
    });
    rerender({ open: true });
    expect(result.current).toEqual({ rendered: true, shown: false, leaving: false });
    frame();
    expect(result.current.shown).toBe(true);
  });

  it('закрытие: уезжает, после leaveMs снимается', () => {
    const { result, rerender } = renderHook(({ open }) => usePresence(open, 200), {
      initialProps: { open: true },
    });
    frame();
    rerender({ open: false });
    expect(result.current).toEqual({ rendered: true, shown: false, leaving: true });
    act(() => vi.advanceTimersByTime(260));
    expect(result.current.rendered).toBe(false);
  });

  it('открыли снова, пока уезжало, — остаётся', () => {
    const { result, rerender } = renderHook(({ open }) => usePresence(open, 200), {
      initialProps: { open: true },
    });
    frame();
    rerender({ open: false });
    rerender({ open: true });
    act(() => vi.advanceTimersByTime(300));
    expect(result.current).toEqual({ rendered: true, shown: true, leaving: false });
  });
});
