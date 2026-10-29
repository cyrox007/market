import { renderHook, waitFor } from '@testing-library/react';
import { SWRConfig } from 'swr';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it } from 'vitest';
import type { Category } from '../lib/api';
import { useMenuTree } from './useMenuTree';

/** Узел в том виде, в каком его отдаёт `/categories/tree`: с SEO и копией родителя */
const node = (id: number, slug: string, children: Category[] = []): Category =>
  ({
    id,
    name: `Раздел ${id}`,
    slug,
    image: `/img/${slug}.jpg`,
    image_thumb: `/img/${slug}-thumb.jpg`,
    image_hd: null,
    image_fullhd: null,
    icon: null,
    description: 'Длинное описание раздела, которое меню не показывает',
    products_count: 12,
    regional: { text: null, city: null, count_source: 'not_requested' },
    seo: { title: 'Раздел – Светофор Мебели', description: 'Длинный SEO-текст', locale: 'ru_RU' },
    parent: id === 1 ? null : node(1, 'root'),
    full_path: `/catalog/${slug}`,
    children,
  }) as unknown as Category;

const tree = [node(1, 'root', [node(2, 'child')])];

const wrapper = ({ children }: { children: ReactNode }) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
);

beforeEach(() => localStorage.clear());

const render = (key = 'header:test-tree') =>
  renderHook(() => useMenuTree(`/api/${key}`, key, async () => ({ tree })), { wrapper });

describe('дерево для шапки', () => {
  it('отдаёт дерево как есть — страницам нужны и картинки разделов', async () => {
    const { result } = render();
    await waitFor(() => expect(result.current.tree).toHaveLength(1));

    expect(result.current.tree[0].seo).toBeTruthy();
    expect(result.current.tree[0].image_thumb).toBe('/img/root-thumb.jpg');
  });

  it('в хранилище кладёт только нужное меню: без seo, parent и описаний', async () => {
    const { result } = render('header:slim-tree');
    await waitFor(() => expect(result.current.tree).toHaveLength(1));

    const saved = JSON.parse(localStorage.getItem('header:slim-tree')!);
    const [root] = saved.tree;

    expect(Object.keys(root).sort()).toEqual(
      ['children', 'id', 'image', 'image_hd', 'image_thumb', 'name', 'slug'].sort(),
    );
    expect(root.children[0].slug).toBe('child');
    // Вес ответа держат именно они (market-docs/62)
    expect(JSON.stringify(saved)).not.toContain('seo');
    expect(JSON.stringify(saved)).not.toContain('Длинное описание');
  });

  it('прочитанное из хранилища дерево отдаётся до ответа сервера', async () => {
    localStorage.setItem(
      'header:stored-tree',
      JSON.stringify({ savedAt: Date.now(), tree: [{ id: 9, name: 'Из кэша', slug: 'cached' }] }),
    );
    const never = new Promise<{ tree: Category[] }>(() => {});
    const { result } = renderHook(
      () => useMenuTree('/api/stored', 'header:stored-tree', () => never),
      { wrapper },
    );

    await waitFor(() => expect(result.current.tree).toHaveLength(1));
    expect(result.current.tree[0].slug).toBe('cached');
  });
});
