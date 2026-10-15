import { describe, expect, it } from 'vitest';
import type { Category } from '../../lib/api';
import { pageView } from './pageView';

const divany = { slug: 'divany', children: [{ slug: 'pryamye-divany' }] } as unknown as Category;
const pryamye = { slug: 'pryamye-divany', children: [] } as unknown as Category;

describe('что показывает страница раздела', () => {
  it('раздел ещё не загружен — заглушка, а не список с «Каталогом»', () => {
    expect(pageView({ slug: 'divany', category: null, isRooms: false, notFound: false })).toBe(
      'loading',
    );
  });

  it('на экране прежний раздел, новый грузится — тоже заглушка', () => {
    expect(pageView({ slug: 'divany', category: pryamye, isRooms: false, notFound: false })).toBe(
      'loading',
    );
  });

  it('с подкатегориями — лендинг, без — список', () => {
    expect(pageView({ slug: 'divany', category: divany, isRooms: false, notFound: false })).toBe(
      'landing',
    );
    expect(
      pageView({ slug: 'pryamye-divany', category: pryamye, isRooms: false, notFound: false }),
    ).toBe('listing');
  });

  it('комната — всегда список', () => {
    const room = { ...divany, slug: 'gostinaya' } as Category;
    expect(pageView({ slug: 'gostinaya', category: room, isRooms: true, notFound: false })).toBe(
      'listing',
    );
  });

  it('не найден — не найден', () => {
    expect(pageView({ slug: 'net', category: null, isRooms: false, notFound: true })).toBe(
      'notFound',
    );
  });
});
