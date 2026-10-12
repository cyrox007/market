import { X } from 'lucide-react';
import type { FiltersMeta } from '../../../../lib/api';
import {
  filterChips,
  removeChip,
  resetFilters,
  type CatalogQuery,
} from '../../../../lib/catalog-query';

interface FilterChipsProps {
  /** Применённые фильтры — из адреса */
  query: CatalogQuery;
  meta: FiltersMeta | null;
  /** Подкатегории — их названий нет в meta.filters */
  subcategories?: { slug: string; name: string }[];
  onQueryChange: (next: CatalogQuery) => void;
}

/**
 * Плашки выбранных фильтров и «Сбросить всё» (market-docs/49): снимаются сразу, без черновика.
 * От 550 — с переносом, «Сбросить всё» в конце; ниже — плашки одной строкой со свайпом,
 * «Сбросить всё» рядом ниже.
 */
export default function FilterChips({
  query,
  meta,
  subcategories,
  onQueryChange,
}: FilterChipsProps) {
  const chips = filterChips(query, { meta, categories: subcategories });
  if (chips.length === 0) return null;

  return (
    <div className="flex flex-wrap items-center gap-2 max-vsm:flex-col max-vsm:flex-nowrap max-vsm:items-start max-vsm:gap-1">
      {/* role — у `display: contents` Safari теряет список */}
      <ul
        role="list"
        className="vsm:contents max-vsm:-mx-4 max-vsm:flex max-vsm:gap-2 max-vsm:self-stretch max-vsm:overflow-x-auto max-vsm:px-4 max-vsm:[scrollbar-width:none]"
      >
        {chips.map((chip) => (
          <li key={chip.id} className="shrink-0 motion-safe:animate-fadeIn">
            <button
              type="button"
              aria-label={`Убрать фильтр «${chip.label}»`}
              onClick={() => onQueryChange(removeChip(query, chip.id))}
              className="inline-flex items-center gap-2 whitespace-nowrap rounded-pill bg-surface-grey py-2 pl-[14px] pr-3 text-14 font-medium text-ink outline-none transition-colors hover:bg-surface-border focus-visible:ring-2 focus-visible:ring-brand-green"
            >
              {chip.label}
              <X aria-hidden className="size-4 shrink-0" />
            </button>
          </li>
        ))}
      </ul>
      <button
        type="button"
        onClick={() => onQueryChange(resetFilters(query))}
        className="rounded-pill px-[14px] py-2 text-14 font-medium text-ink-secondary outline-none transition-colors hover:text-ink-secondary/60 focus-visible:ring-2 focus-visible:ring-brand-green"
      >
        Сбросить всё
      </button>
    </div>
  );
}
