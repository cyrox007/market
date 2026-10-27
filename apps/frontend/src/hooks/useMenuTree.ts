import { useEffect, useState } from 'react';
import useSWR from 'swr';
import type { Category } from '../lib/api';

const STORAGE_TTL = 24 * 60 * 60 * 1000;

/**
 * В хранилище — только то, чем живут меню и карусель разделов: подпись, адрес и картинка.
 * Ответ `/categories/tree` весит 451 КБ, и 81 % из них — копия родителя в каждом ребёнке,
 * SEO и региональные поля (замеры — market-docs/62). Их незачем держать и разбирать
 * при каждом запуске.
 */
function slimTree(nodes: Category[]): Category[] {
  return nodes.map(
    (node) =>
      ({
        id: node.id,
        name: node.name,
        slug: node.slug,
        image: node.image ?? null,
        image_thumb: node.image_thumb ?? null,
        image_hd: node.image_hd ?? null,
        children: slimTree(node.children ?? []),
      }) as unknown as Category,
  );
}

/**
 * Дерево для шапки (категории или комнаты) — один запрос на всё приложение.
 * Источники по убыванию скорости: localStorage → запрос к API.
 */
export function useMenuTree(
  swrKey: string,
  storageKey: string,
  fetcher: () => Promise<{ tree: Category[] }>,
) {
  const [stored, setStored] = useState<Category[] | null>(null);

  // Читаем хранилище после гидрации, чтобы разметка не разошлась с серверной.
  useEffect(() => {
    try {
      const raw = localStorage.getItem(storageKey);
      if (!raw) return;
      const parsed = JSON.parse(raw) as { savedAt: number; tree: Category[] };
      if (parsed?.tree?.length && Date.now() - parsed.savedAt <= STORAGE_TTL) {
        setStored(parsed.tree);
      }
    } catch {
      // приватный режим или битые данные — не повод ломать шапку
    }
  }, [storageKey]);

  const { data, isLoading } = useSWR(swrKey, fetcher, {
    revalidateIfStale: false,
    revalidateOnFocus: false,
    dedupingInterval: 60_000,
  });

  useEffect(() => {
    if (data?.tree?.length) {
      try {
        localStorage.setItem(
          storageKey,
          JSON.stringify({ savedAt: Date.now(), tree: slimTree(data.tree) }),
        );
      } catch {
        // переполненное хранилище — игнорируем
      }
    }
  }, [data, storageKey]);

  return { tree: data?.tree ?? stored ?? [], isLoading };
}
