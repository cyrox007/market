/**
 * Одна очередь запросов, меняющих сессию (корзина, избранное, сравнение): строго по одному.
 * Параллельные запросы затирают сессию Laravel друг другу. market-docs/31, 32
 */
let chain: Promise<unknown> = Promise.resolve();
let pending = 0;
const idleListeners = new Set<() => void>();

const settle = () => {
  pending -= 1;
  if (pending === 0) idleListeners.forEach((listener) => listener());
};

export function runSessionTask<T>(task: () => Promise<T>): Promise<T> {
  pending += 1;
  const run = chain.then(() => task());
  chain = run.then(settle, settle);
  return run;
}

export function isSessionBusy(): boolean {
  return pending > 0;
}

/** Очередь опустела — последняя задача завершилась. Возвращает отписку */
export function onIdle(listener: () => void): () => void {
  idleListeners.add(listener);
  return () => {
    idleListeners.delete(listener);
  };
}

/** Сброс очереди (тесты) */
export function resetSessionQueue(): void {
  chain = Promise.resolve();
  pending = 0;
  idleListeners.clear();
}
