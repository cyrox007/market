import { useEffect, useState } from 'react';
import { filtersSignature, resetFilters, type CatalogQuery } from '../../../lib/catalog-query';

export interface FilterDraftOptions {
  applied: CatalogQuery;
  /** Сколько товаров по применённым фильтрам — без запроса, пока черновик не менялся; null — список грузится */
  appliedTotal: number | null;
  onApply: (next: CatalogQuery) => void;
  countProducts: (query: CatalogQuery) => Promise<number>;
  delayMs?: number;
  /** Окно открыто сразу — панель, которую монтируют только открытой */
  initialSurface?: string;
}

/** Черновик фильтров до «Показать N товаров»; число — с задержкой, устаревшие ответы отброшены (market-docs/46) */
export function useFilterDraft({
  applied,
  appliedTotal,
  onApply,
  countProducts,
  delayMs = 400,
  initialSurface,
}: FilterDraftOptions) {
  const [surface, setSurface] = useState<string | null>(initialSurface ?? null);
  const [draft, setDraft] = useState(applied);
  const [counted, setCounted] = useState<{ signature: string; value: number | null } | null>(null);

  const draftSignature = filtersSignature(draft);
  const isApplied = draftSignature === filtersSignature(applied);

  useEffect(() => {
    if (surface === null || isApplied) return;
    let cancelled = false;
    const timer = setTimeout(() => {
      countProducts(draft)
        .catch(() => null)
        .then((value) => {
          if (!cancelled) setCounted({ signature: draftSignature, value });
        });
    }, delayMs);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
    // draft входит в draftSignature
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [surface, isApplied, draftSignature, delayMs, countProducts]);

  const open = (next: string) => {
    setDraft(applied);
    setSurface(next);
  };

  const set = (patch: Partial<CatalogQuery>) =>
    setDraft((prev) => ({ ...prev, ...patch, page: 1 }));

  const reset = () => setDraft((prev) => resetFilters(prev));

  const apply = () => {
    onApply({ ...draft, page: 1 });
    setSurface(null);
  };

  const discard = () => setSurface(null);

  const count = isApplied
    ? { value: appliedTotal, loading: appliedTotal === null }
    : counted?.signature === draftSignature
      ? { value: counted.value, loading: false }
      : { value: null, loading: true };

  return { surface, draft, open, set, reset, apply, discard, count };
}

export type FilterDraft = ReturnType<typeof useFilterDraft>;
