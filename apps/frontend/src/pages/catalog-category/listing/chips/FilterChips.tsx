import { X } from 'lucide-react';
import { useState, type TransitionEvent } from 'react';
import type { FiltersMeta } from '../../../../lib/api';
import { cn } from '../../../../lib/cn';
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
  /** Отступ сверху — внутри сворачиваемой части, чтобы уезжал вместе со строкой */
  offsetClassName?: string;
}

/** Плашки выбранных фильтров и «Сбросить всё» — снимаются сразу, без черновика (market-docs/49) */
export default function FilterChips({
  query,
  meta,
  subcategories,
  onQueryChange,
  offsetClassName,
}: FilterChipsProps) {
  const chips = filterChips(query, { meta, categories: subcategories });
  const open = chips.length > 0;

  // Последние плашки — чтобы строка сворачивалась с ними, а не пустой
  const key = chips.map((c) => `${c.id}:${c.label}`).join('|');
  const [kept, setKept] = useState({ key, chips });
  if (open && kept.key !== key) setKept({ key, chips });
  const shown = open ? chips : kept.chips;

  // Пока едет — обрезаем; раскрылось — нет: строка ниже 550 выходит за поля, у плашек кольцо фокуса
  const [settled, setSettled] = useState(open);
  const [prevOpen, setPrevOpen] = useState(open);
  if (prevOpen !== open) {
    setPrevOpen(open);
    setSettled(false);
  }
  const onTransitionEnd = (event: TransitionEvent<HTMLDivElement>) => {
    if (event.target === event.currentTarget && event.propertyName === 'grid-template-rows') {
      setSettled(open);
    }
  };

  return (
    <div
      inert={!open}
      onTransitionEnd={onTransitionEnd}
      className={cn(
        'grid grid-cols-1 transition-[grid-template-rows,opacity,visibility] duration-slow ease-out',
        'motion-reduce:transition-none',
        open ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0',
      )}
      style={{ visibility: open ? 'visible' : 'hidden' }}
    >
      {/* Без анимации transitionend не приходит — обрезать незачем: свёрнутое скрыто visibility */}
      <div
        className={cn(
          'min-h-0 motion-reduce:overflow-visible',
          !(open && settled) && 'overflow-hidden',
        )}
      >
        {shown.length > 0 && (
          <div className={offsetClassName}>
            <ChipRow chips={shown} query={query} onQueryChange={onQueryChange} />
          </div>
        )}
      </div>
    </div>
  );
}

function ChipRow({
  chips,
  query,
  onQueryChange,
}: {
  chips: ReturnType<typeof filterChips>;
  query: CatalogQuery;
  onQueryChange: (next: CatalogQuery) => void;
}) {
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
