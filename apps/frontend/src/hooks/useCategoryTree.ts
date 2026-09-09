import { useEffect, useState } from 'react';
import useSWR from 'swr';
import { api } from '../lib/api';
import type { Category } from '../lib/api';

/** Ключ один на всё приложение: шапка живёт на всех страницах, запрос должен быть один */
export const CATEGORY_TREE_KEY = '/api/categories/tree';

const STORAGE_KEY = 'header:category-tree';
const STORAGE_TTL = 24 * 60 * 60 * 1000;

interface StoredTree {
  savedAt: number;
  tree: Category[];
}

function readStored(): Category[] | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as StoredTree;
    if (!parsed?.tree?.length) return null;
    if (Date.now() - parsed.savedAt > STORAGE_TTL) return null;
    return parsed.tree;
  } catch {
    return null;
  }
}

function writeStored(tree: Category[]) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ savedAt: Date.now(), tree }));
  } catch {
    // приватный режим или переполненное хранилище — не повод ломать шапку
  }
}

/**
 * Дерево категорий для шапки.
 *
 * Три источника по убыванию скорости: данные серверного рендеринга, прошлый
 * ответ из localStorage, запрос к API. Зачем нужен второй — market-docs/14-header-wiring.md.
 */
export function useCategoryTree() {
  const [stored, setStored] = useState<Category[] | null>(null);

  // Читаем хранилище после гидрации: если сделать это при первом рендере,
  // разметка разойдётся с серверной, где дерева могло не быть.
  useEffect(() => setStored(readStored()), []);

  const { data, isLoading } = useSWR(CATEGORY_TREE_KEY, () => api.categories.tree(), {
    revalidateIfStale: false,
    revalidateOnFocus: false,
    dedupingInterval: 60_000,
  });

  useEffect(() => {
    if (data?.tree?.length) writeStored(data.tree);
  }, [data]);

  return { categories: data?.tree ?? stored ?? [], isLoading };
}
