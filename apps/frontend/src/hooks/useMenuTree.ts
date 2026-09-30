import { useEffect, useState } from 'react';
import useSWR from 'swr';
import type { Category } from '../lib/api';

const STORAGE_TTL = 24 * 60 * 60 * 1000;

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
        localStorage.setItem(storageKey, JSON.stringify({ savedAt: Date.now(), tree: data.tree }));
      } catch {
        // переполненное хранилище — игнорируем
      }
    }
  }, [data, storageKey]);

  return { tree: data?.tree ?? stored ?? [], isLoading };
}
