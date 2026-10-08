import { beforeEach, describe, expect, it } from 'vitest';
import { isSessionBusy, onIdle, resetSessionQueue, runSessionRead, runSessionTask } from './session-queue';
import { deferred, flush } from '../test/deferred';

describe('session-queue', () => {
  beforeEach(() => {
    resetSessionQueue();
  });

  it('starts the next task only after the previous one finishes', async () => {
    const first = deferred<string>();
    const started: string[] = [];

    const a = runSessionTask(() => {
      started.push('a');
      return first.promise;
    });
    const b = runSessionTask(async () => {
      started.push('b');
      return 'b';
    });

    await flush();
    expect(started).toEqual(['a']);

    first.resolve('a');
    await expect(a).resolves.toBe('a');
    await expect(b).resolves.toBe('b');
    expect(started).toEqual(['a', 'b']);
  });

  it('passes a failed task error to its caller and still runs the next task', async () => {
    const failed = runSessionTask(() => Promise.reject(new Error('500')));
    const next = runSessionTask(async () => 'ok');

    await expect(failed).rejects.toThrow('500');
    await expect(next).resolves.toBe('ok');
  });

  it('is busy while any task is queued or in flight', async () => {
    const first = deferred<void>();
    const second = deferred<void>();
    expect(isSessionBusy()).toBe(false);

    const a = runSessionTask(() => first.promise);
    const b = runSessionTask(() => second.promise);
    expect(isSessionBusy()).toBe(true);

    first.resolve();
    await a;
    expect(isSessionBusy()).toBe(true);

    second.resolve();
    await b;
    await flush();
    expect(isSessionBusy()).toBe(false);
  });

  it('notifies idle once after the last queued task, not between tasks', async () => {
    const first = deferred<void>();
    const second = deferred<void>();
    let idleCalls = 0;
    onIdle(() => {
      idleCalls += 1;
    });

    const a = runSessionTask(() => first.promise);
    const b = runSessionTask(() => second.promise);

    first.resolve();
    await a;
    await flush();
    expect(idleCalls).toBe(0);

    second.resolve();
    await b;
    await flush();
    expect(idleCalls).toBe(1);
  });

  it('stops notifying idle after unsubscribe', async () => {
    let idleCalls = 0;
    const unsubscribe = onIdle(() => {
      idleCalls += 1;
    });

    await runSessionTask(async () => undefined);
    await flush();
    unsubscribe();
    await runSessionTask(async () => undefined);
    await flush();

    expect(idleCalls).toBe(1);
  });

  it('runs reads side by side', async () => {
    const first = deferred<void>();
    const started: string[] = [];

    void runSessionRead(() => {
      started.push('a');
      return first.promise;
    });
    void runSessionRead(async () => {
      started.push('b');
    });
    await flush();

    expect(started).toEqual(['a', 'b']);
  });

  it('never overlaps a read with a write', async () => {
    const read1 = deferred<void>();
    const write = deferred<void>();
    const started: string[] = [];

    void runSessionRead(() => {
      started.push('read1');
      return read1.promise;
    });
    void runSessionTask(() => {
      started.push('write');
      return write.promise;
    });
    void runSessionRead(async () => {
      started.push('read2');
    });
    await flush();
    expect(started).toEqual(['read1']);

    read1.resolve();
    await flush();
    expect(started).toEqual(['read1', 'write']);

    write.resolve();
    await flush();
    expect(started).toEqual(['read1', 'write', 'read2']);
  });
});
