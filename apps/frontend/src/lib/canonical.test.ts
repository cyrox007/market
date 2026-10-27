import { describe, expect, it } from 'vitest';
import { canonicalForPage } from './canonical';

const section = 'https://new.svetofor-mebel.ru/catalog/divany';

describe('canonicalForPage', () => {
  it('первая страница и адрес без параметров — адрес раздела как есть', () => {
    expect(canonicalForPage(section, '')).toBe(section);
    expect(canonicalForPage(section, '?page=1')).toBe(section);
  });

  it('вторая и дальше — тот же адрес с номером страницы', () => {
    expect(canonicalForPage(section, '?page=2')).toBe(`${section}?page=2`);
    expect(canonicalForPage(section, '?page=12')).toBe(`${section}?page=12`);
  });

  it('сортировка и фильтры в canonical не попадают', () => {
    expect(canonicalForPage(section, '?colors=seryi&sort=cheap&page=3')).toBe(`${section}?page=3`);
    expect(canonicalForPage(section, '?colors=seryi&sort=cheap')).toBe(section);
  });

  it('мусор в page игнорируется', () => {
    for (const search of ['?page=0', '?page=-2', '?page=abc', '?page=2.5', '?page=']) {
      expect(canonicalForPage(section, search)).toBe(section);
    }
  });

  it('параметры из самого canonical не копятся', () => {
    expect(canonicalForPage(`${section}?page=2`, '?page=5')).toBe(`${section}?page=5`);
  });

  it('пустой canonical остаётся пустым — адрес подставит вызывающий', () => {
    expect(canonicalForPage(null, '?page=3')).toBeNull();
    expect(canonicalForPage(undefined, '?page=3')).toBeUndefined();
    expect(canonicalForPage('', '?page=3')).toBe('');
  });
});
