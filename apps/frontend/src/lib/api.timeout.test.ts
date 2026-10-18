import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from './api';

const signals: (AbortSignal | undefined)[] = [];

beforeEach(() => {
  signals.length = 0;
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
  vi.stubGlobal(
    'fetch',
    vi.fn((_input: RequestInfo | URL, init?: RequestInit) => {
      signals.push(init?.signal ?? undefined);
      return new Promise<Response>((_resolve, reject) => {
        init?.signal?.addEventListener('abort', () =>
          reject(new DOMException('The operation was aborted.', 'AbortError')),
        );
      });
    }),
  );
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
});

describe('fetchAPI: предел ожидания ответа', () => {
  it('молчащий сервер через 20 с даёт ошибку 504', async () => {
    const request = api.products.get('test-product');
    const settled = expect(request).rejects.toMatchObject({ status: 504 });
    await vi.advanceTimersByTimeAsync(20_000);
    await settled;
  });

  it('маршруты очереди сессии и вход не обрывает', () => {
    void api.cart.get();
    void api.cart.count();
    void api.wishlist.list();
    void api.compare.list();
    void api.counters.all();
    void api.orders.list();
    expect(signals).toEqual([undefined, undefined, undefined, undefined, undefined, undefined]);
  });

  it('чужой signal не подменяет', () => {
    const controller = new AbortController();
    void api.localities.search('Лип', controller.signal);
    expect(signals).toEqual([controller.signal]);
  });
});
