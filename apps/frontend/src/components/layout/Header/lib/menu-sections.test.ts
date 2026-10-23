import { describe, expect, it } from 'vitest';
import type { Category } from '../../../../lib/api';
import { toMenuSections } from './menu-sections';

const category = (slug: string, name: string, children: Category[] = []) =>
  ({ id: slug.length, slug, name, children }) as unknown as Category;

/** Дерево как в проде: Мягкая мебель → Диваны → Прямые диваны */
const tree = [
  category('miagkaia-mebel', 'Мягкая мебель', [
    category('divany', 'Диваны', [
      category('pryamye-divany', 'Прямые диваны'),
      category('uglovye-divany', 'Угловые диваны'),
    ]),
    category('kresla', 'Кресла'),
  ]),
  category('stok', 'Сток'),
];

describe('toMenuSections', () => {
  it('раздел с подразделами остаётся ссылкой на себя', () => {
    expect(toMenuSections(tree, '/catalog')[0]).toMatchObject({
      id: 'miagkaia-mebel',
      to: '/catalog/miagkaia-mebel',
    });
  });

  it('подраздел с детьми — колонка с заголовком, его дети — ссылки', () => {
    const [group] = toMenuSections(tree, '/catalog')[0].groups!;

    expect(group).toMatchObject({ title: 'Диваны', titleTo: '/catalog/divany' });
    expect(group.links.map((link) => link.to)).toEqual([
      '/catalog/pryamye-divany',
      '/catalog/uglovye-divany',
    ]);
  });

  it('подразделы без детей собираются в колонку без заголовка', () => {
    const groups = toMenuSections(tree, '/catalog')[0].groups!;

    expect(groups).toHaveLength(2);
    expect(groups[1].title).toBeUndefined();
    expect(groups[1].links[0]).toMatchObject({ to: '/catalog/kresla', label: 'Кресла' });
  });

  it('раздел без подразделов — просто ссылка, без колонок', () => {
    expect(toMenuSections(tree, '/catalog')[1]).toEqual({
      id: 'stok',
      label: 'Сток',
      to: '/catalog/stok',
    });
  });

  it('тот же разбор у комнат, меняется только начало адреса', () => {
    expect(toMenuSections(tree, '/rooms')[0].groups![0].titleTo).toBe('/rooms/divany');
  });
});
