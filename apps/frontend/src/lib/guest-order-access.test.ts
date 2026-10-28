import { afterEach, expect, it, vi } from 'vitest';
import { consumeGuestOrderLink, guestOrderHeaders, saveGuestOrderToken } from './guest-order-access';
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

it('opens an email link in a fresh browser and removes its secret without losing payment parameters', () => {
  const token = 'b'.repeat(64);
  expect(guestOrderHeaders(701)).toEqual({});
  expect(consumeGuestOrderLink(701, `https://shop.test/orders/701?payment=success#access=${token}`))
    .toBe('/orders/701?payment=success');
  expect(guestOrderHeaders(701)).toEqual({ 'X-Order-Token': token });
  expect(guestOrderHeaders(702)).toEqual({});
});

it('does not replace a valid order capability with malformed input', () => {
  const token = 'c'.repeat(64);
  saveGuestOrderToken(703, token);
  expect(consumeGuestOrderLink(703, 'https://shop.test/orders/703#access=broken&section=items'))
    .toBe('/orders/703#section=items');
  expect(guestOrderHeaders(703)).toEqual({ 'X-Order-Token': token });
  expect(consumeGuestOrderLink(703, 'https://shop.test/orders/703')).toBeNull();
});
