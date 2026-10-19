import { useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { isSheetHistoryEntry } from '../../../components/ui/primitives';
import { parseCatalogQuery, toCatalogSearch, type CatalogQuery } from '../../../lib/catalog-query';

/** Применённый запрос каталога живёт в адресе страницы */
export function useUrlCatalogQuery() {
  const [searchParams, setSearchParams] = useSearchParams();
  const search = searchParams.toString();
  const query = useMemo(() => parseCatalogQuery(search), [search]);
  const setQuery = useCallback(
    (next: CatalogQuery) =>
      setSearchParams(new URLSearchParams(toCatalogSearch(next)), {
        replace: isSheetHistoryEntry(),
      }),
    [setSearchParams],
  );
  return [query, setQuery] as const;
}
