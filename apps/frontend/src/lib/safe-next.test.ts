import { describe, expect, it } from 'vitest';
import { safeNext } from './safe-next';

describe('safeNext — куда вернуть после входа', () => {
  it('внутренний путь с параметрами — как есть', () => {
    expect(safeNext('/product/tumba?variant=3&review=1')).toBe('/product/tumba?variant=3&review=1');
  });

  it('чужой сайт и мусор — null', () => {
    for (const bad of [
      '//evil.com',
      '/\\evil.com',
      'https://evil.com',
      'javascript:alert(1)',
      'product',
      '',
      null,
    ])
      expect(safeNext(bad)).toBeNull();
  });
});
