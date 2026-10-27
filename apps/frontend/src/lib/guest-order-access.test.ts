import { afterEach, expect, it, vi } from 'vitest';
import { guestOrderHeaders, saveGuestOrderToken } from './guest-order-access';
afterEach(() => { localStorage.clear(); vi.useRealTimers(); });
it('keeps a secret per order, not per email, and expires it', () => {
  vi.useFakeTimers();
  const token = 'a'.repeat(64);
  saveGuestOrderToken(123, token);
  expect(guestOrderHeaders(123)).toEqual({ 'X-Order-Token': token });
  expect(guestOrderHeaders(124)).toEqual({});
  vi.advanceTimersByTime(31 * 86400_000);
  expect(guestOrderHeaders(123)).toEqual({});
});
