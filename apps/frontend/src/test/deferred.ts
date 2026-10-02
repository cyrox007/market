/** Промис, который тест разрешает вручную — имитация медленного ответа сервера */
export function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
}

/** Дать отработать всем промисам в очереди */
export const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
