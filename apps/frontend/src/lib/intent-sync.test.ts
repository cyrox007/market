import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createIntentSync } from './intent-sync';
import { resetSessionQueue } from './session-queue';
import { deferred, flush } from '../test/deferred';

function setup() {
  const shown = new Map<number, number>();
  const send = vi.fn<(key: number, value: number, confirmed: number) => Promise<number>>();
  const onError = vi.fn();
  const sync = createIntentSync<number>({
    send,
    apply: (key, value) => shown.set(key, value),
    onError,
  });
  return { sync, send, onError, shown };
}

describe('intent-sync', () => {
  beforeEach(() => {
    resetSessionQueue();
  });

  it('shows the new value at once and sends it to the server', async () => {
    const { sync, send, shown } = setup();
    send.mockResolvedValue(1);

    sync.set(7, 1, 0);

    expect(shown.get(7)).toBe(1);
    await flush();
    expect(send).toHaveBeenCalledTimes(1);
    expect(send).toHaveBeenCalledWith(7, 1, 0);
  });

  it('collects clicks made during a request into one follow-up request', async () => {
    const { sync, send, shown } = setup();
    const firstResponse = deferred<number>();
    send.mockReturnValueOnce(firstResponse.promise).mockResolvedValueOnce(5);

    sync.set(7, 1, 0);
    await flush();
    sync.set(7, 2, 1);
    sync.set(7, 3, 2);
    sync.set(7, 4, 3);
    sync.set(7, 5, 4);
    expect(shown.get(7)).toBe(5);

    firstResponse.resolve(1);
    await flush();

    expect(send.mock.calls).toEqual([
      [7, 1, 0],
      [7, 5, 1],
    ]);
    expect(shown.get(7)).toBe(5);
  });

  it('shows the value the server settled on and does not retry it', async () => {
    const { sync, send, shown } = setup();
    send.mockResolvedValueOnce(3).mockResolvedValue(5);

    sync.set(7, 5, 0);
    await flush();
    await flush();

    expect(send).toHaveBeenCalledTimes(1);
    expect(shown.get(7)).toBe(3);
  });

  it('sends nothing when the value is changed back before the request goes out', async () => {
    const { sync, send, shown } = setup();
    const firstResponse = deferred<number>();
    send.mockReturnValueOnce(firstResponse.promise);

    sync.set(1, 1, 0);
    await flush();
    sync.set(7, 1, 0);
    sync.set(7, 0, 1);
    firstResponse.resolve(1);
    await flush();

    expect(send.mock.calls).toEqual([[1, 1, 0]]);
    expect(shown.get(7)).toBe(0);
  });

  it('rolls back only the failed key and reports the error', async () => {
    const { sync, send, shown, onError } = setup();
    const failure = new Error('500');
    send.mockImplementation(async (key, value) => {
      if (key === 7) throw failure;
      return value;
    });

    sync.set(7, 3, 1);
    sync.set(8, 2, 0);
    await flush();

    expect(shown.get(7)).toBe(1);
    expect(shown.get(8)).toBe(2);
    expect(onError).toHaveBeenCalledTimes(1);
    expect(onError).toHaveBeenCalledWith(7, failure);
  });

  it('reports a key as pending until the server confirms it', async () => {
    const { sync, send } = setup();
    const response = deferred<number>();
    send.mockReturnValueOnce(response.promise);

    expect(sync.isPending(7)).toBe(false);
    sync.set(7, 1, 0);
    expect(sync.isPending(7)).toBe(true);
    expect(sync.isPending(8)).toBe(false);

    response.resolve(1);
    await flush();
    expect(sync.isPending(7)).toBe(false);
  });

  it('uses the current value from the caller once nothing is pending', async () => {
    const { sync, send } = setup();
    send.mockImplementation(async (_key, value) => value);

    sync.set(7, 1, 0);
    await flush();
    // Сверка с сервером показала 0 (убрали в другой вкладке) — клик снова ставит 1
    sync.set(7, 1, 0);
    await flush();

    expect(send.mock.calls).toEqual([
      [7, 1, 0],
      [7, 1, 0],
    ]);
  });
});
