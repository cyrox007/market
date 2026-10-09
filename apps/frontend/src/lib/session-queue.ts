/**
 * Очередь запросов к сессионным маршрутам (корзина, избранное, сравнение, счётчики):
 * мутации строго по одной, чтения параллельно друг с другом, но не с мутацией.
 * Параллельные запросы затирают сессию Laravel друг другу. market-docs/31, 32
 */

/** Завершится, когда отработает всё поставленное: и мутации, и чтения */
let chain: Promise<unknown> = Promise.resolve();
/** Завершится, когда отработает последняя поставленная мутация */
let writesDone: Promise<unknown> = Promise.resolve();
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
  writesDone = chain;
  return run;
}

/**
 * GET сессионного маршрута. Сервер сохраняет сессию и после GET — снимком на момент начала,
 * поэтому чтение не должно пересекаться с мутацией. Чтения между собой — параллельно.
 */
export function runSessionRead<T>(task: () => Promise<T>): Promise<T> {
  pending += 1;
  const run = writesDone.then(() => task());
  // Следующая мутация дождётся и этого чтения
  chain = Promise.all([chain, run.then(settle, settle)]);
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
  writesDone = Promise.resolve();
  pending = 0;
  idleListeners.clear();
}
