import { describe, expect, it } from 'vitest';
import { sameParams } from './sameParams';

describe('тот же запрос к API', () => {
  it('порядок ключей не важен, в том числе во вложенных', () => {
    expect(
      sameParams(
        { category_slug: 'divany', page: 1, attributes: { a: ['1'], b: ['2'] } },
        { attributes: { b: ['2'], a: ['1'] }, page: 1, category_slug: 'divany' },
      ),
    ).toBe(true);
  });

  it('ключ со значением undefined — как отсутствующий', () => {
    expect(sameParams({ page: 1, region_id: undefined }, { page: 1 })).toBe(true);
  });

  it('другое значение или порядок в списке — другой запрос', () => {
    expect(sameParams({ page: 1 }, { page: 2 })).toBe(false);
    expect(sameParams({ colors: ['red', 'blue'] }, { colors: ['blue', 'red'] })).toBe(false);
  });
});
