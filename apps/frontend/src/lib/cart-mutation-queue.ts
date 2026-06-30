/**
 * Последовательная очередь мутаций корзины.
 * Исключает гонки при быстрых кликах «В корзину» (ответы API приходят не по порядку).
 */
let chain: Promise<unknown> = Promise.resolve();

export function runCartMutation<T>(task: () => Promise<T>): Promise<T> {
  const run = chain.then(() => task());
  chain = run.then(
    () => undefined,
    () => undefined,
  );
  return run;
}

/** Сброс очереди (тесты) */
export function resetCartMutationQueue(): void {
  chain = Promise.resolve();
}
