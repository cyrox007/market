import { api } from '../lib/api';
import { useMenuTree } from './useMenuTree';

/** Ключ один на всё приложение: шапка живёт на всех страницах, запрос должен быть один */
export const CATEGORY_TREE_KEY = '/api/categories/tree';

/** Дерево категорий для шапки. */
export function useCategoryTree() {
  const { tree, isLoading } = useMenuTree(CATEGORY_TREE_KEY, 'header:category-tree', () =>
    api.categories.tree(),
  );

  return { categories: tree, isLoading };
}
