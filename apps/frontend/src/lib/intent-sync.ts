import { runSessionTask } from './session-queue';

/**
 * Синхронизатор намерений: экран меняется сразу, на сервер уходит итоговое значение
 * через общую очередь сессии. market-docs/31 §4, 32
 */
interface IntentSyncOptions<V> {
  /** Отправить значение; вернуть то, что подтвердил сервер */
  send: (key: number, value: V, confirmed: V) => Promise<V>;
  /** Показать значение — правит одну запись текущего кэша */
  apply: (key: number, value: V) => void;
  onError: (key: number, error: unknown) => void;
}

interface Entry<V> {
  desired: V;
  confirmed: V;
  /** Задача по ключу уже стоит в очереди — новые клики только меняют desired */
  queued: boolean;
  inFlight: boolean;
}

export function createIntentSync<V>({ send, apply, onError }: IntentSyncOptions<V>) {
  const entries = new Map<number, Entry<V>>();

  const flush = (key: number) =>
    runSessionTask(async () => {
      const entry = entries.get(key)!;
      entry.queued = false;
      // Кликнули туда-обратно — сервер уже в нужном состоянии
      if (Object.is(entry.desired, entry.confirmed)) return;
      const target = entry.desired;
      entry.inFlight = true;
      try {
        entry.confirmed = await send(key, target, entry.confirmed);
      } catch (error) {
        // Откат только этого ключа; если успели кликнуть ещё — пробуем новое значение
        if (Object.is(entry.desired, target)) {
          entry.desired = entry.confirmed;
          apply(key, entry.confirmed);
        }
        onError(key, error);
      } finally {
        entry.inFlight = false;
      }
      // Новых кликов не было — принимаем ответ сервера (мог урезать по остатку)
      if (Object.is(entry.desired, target) && !Object.is(target, entry.confirmed)) {
        entry.desired = entry.confirmed;
        apply(key, entry.confirmed);
      }
      if (!Object.is(entry.desired, entry.confirmed)) schedule(key);
    });

  const schedule = (key: number) => {
    const entry = entries.get(key)!;
    if (entry.queued) return;
    entry.queued = true;
    void flush(key);
  };

  return {
    set(key: number, value: V, confirmedNow: V) {
      const known = entries.get(key);
      // Ничего не ждём от сервера — значение вызывающего свежее (сверка, другая вкладка)
      const idle = !known || (!known.queued && !known.inFlight && Object.is(known.desired, known.confirmed));
      const entry: Entry<V> = idle
        ? { desired: confirmedNow, confirmed: confirmedNow, queued: false, inFlight: false }
        : known;
      entries.set(key, entry);
      entry.desired = value;
      apply(key, value);
      schedule(key);
    },
    isPending(key: number) {
      const entry = entries.get(key);
      return !!entry && !Object.is(entry.desired, entry.confirmed);
    },
  };
}
