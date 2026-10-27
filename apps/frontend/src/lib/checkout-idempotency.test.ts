import { beforeEach, describe, expect, it, vi } from 'vitest';
import { checkoutIdempotencyKey } from './checkout-idempotency';

describe('checkout retry keys', () => {
  beforeEach(() => { sessionStorage.clear(); vi.useRealTimers(); });
  it('reuses the key for a network retry, separates changed checkout data', async () => {
    const body = JSON.stringify({ product_id: 1, quantity: 1 });
    const key = await checkoutIdempotencyKey(body);
    expect(key).toMatch(/^[a-f0-9]{32}$/);
    expect(await checkoutIdempotencyKey(body)).toBe(key);
    expect(await checkoutIdempotencyKey(JSON.stringify({ product_id: 1, quantity: 2 }))).not.toBe(key);
  });
  it('reuses one key for simultaneous clicks', async () => {
    const keys = await Promise.all([checkoutIdempotencyKey('concurrent'), checkoutIdempotencyKey('concurrent')]);
    expect(keys[0]).toBe(keys[1]);
  });
});
