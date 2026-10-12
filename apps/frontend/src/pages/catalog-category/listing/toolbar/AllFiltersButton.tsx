import { Filter } from 'lucide-react';
import FilterPill from './FilterPill';

interface AllFiltersButtonProps {
  count: number;
  onClick: () => void;
}

/** «Фильтры» с числом выбранных — открывает панель «Все фильтры» (задача 04) */
export default function AllFiltersButton({ count, onClick }: AllFiltersButtonProps) {
  return (
    <FilterPill
      chevron={false}
      textBelowMd
      icon={<Filter aria-hidden className="size-4 shrink-0" />}
      aria-label={count ? `Фильтры, выбрано ${count}` : 'Фильтры'}
      onClick={onClick}
    >
      Фильтры
      {count > 0 && (
        <span
          aria-hidden="true"
          className="inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-pill bg-brand-yellow px-1 text-12 font-semibold text-ink max-md:bg-ink max-md:text-ink-inverse"
        >
          {count}
        </span>
      )}
    </FilterPill>
  );
}
