import { describe, expect, it } from 'vitest';
import { pageNumbers } from './pageNumbers';

describe('номера страниц', () => {
  it('первая из 16 — как на макете: 1 2 3 … 16', () => {
    expect(pageNumbers({ from: 1, to: 1 }, 16)).toEqual([1, 2, 3, 'gap', 16]);
  });

  it('середина — соседи текущей, первая и последняя', () => {
    expect(pageNumbers({ from: 8, to: 8 }, 16)).toEqual([1, 'gap', 7, 8, 9, 'gap', 16]);
  });

  it('после «Показать ещё» соседи считаются от всего диапазона', () => {
    expect(pageNumbers({ from: 5, to: 7 }, 16)).toEqual([1, 'gap', 4, 5, 6, 7, 8, 'gap', 16]);
  });

  it('пропуск в одну страницу показывается номером, не многоточием', () => {
    expect(pageNumbers({ from: 4, to: 4 }, 16)).toEqual([1, 2, 3, 4, 5, 'gap', 16]);
  });

  it('последняя — зеркально первой', () => {
    expect(pageNumbers({ from: 16, to: 16 }, 16)).toEqual([1, 'gap', 14, 15, 16]);
  });

  it('мало страниц — все подряд', () => {
    expect(pageNumbers({ from: 1, to: 1 }, 2)).toEqual([1, 2]);
  });
});
