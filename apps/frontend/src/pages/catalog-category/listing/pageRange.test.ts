import { describe, expect, it } from 'vitest';
import { resolvePageRange, type PageRange } from './pageRange';

const shown: PageRange = { from: 2, to: 3, filters: 'sort=cheap' };

describe('какие страницы на экране', () => {
  it('та же страница и те же фильтры — прежний диапазон', () => {
    expect(resolvePageRange(shown, 3, 'sort=cheap', null)).toBe(shown);
  });

  it('«Показать ещё» следующей страницы — дописывает', () => {
    expect(resolvePageRange(shown, 4, 'sort=cheap', 4)).toEqual({
      from: 2,
      to: 4,
      filters: 'sort=cheap',
    });
  });

  it('переход по номеру — заменяет, даже на следующую', () => {
    expect(resolvePageRange(shown, 4, 'sort=cheap', null)).toEqual({
      from: 4,
      to: 4,
      filters: 'sort=cheap',
    });
  });

  it('дописывать можно только соседнюю страницу', () => {
    expect(resolvePageRange(shown, 6, 'sort=cheap', 6)).toMatchObject({ from: 6, to: 6 });
  });

  it('сменились фильтры — с начала, даже если идёт «Показать ещё»', () => {
    expect(resolvePageRange(shown, 4, 'on_sale=1', 4)).toEqual({
      from: 4,
      to: 4,
      filters: 'on_sale=1',
    });
  });
});
