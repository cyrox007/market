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
      icon={<Filter aria-hidden className="size-5 shrink-0" />}
      aria-label={count ? `Фильтры, выбрано ${count}` : 'Фильтры'}
      onClick={onClick}
    >
      Фильтры
      {count > 0 && (
        <span
          aria-hidden="true"
          className="inline-flex size-5 items-center justify-center rounded-full bg-brand-yellow text-12 font-medium"
        >
          {count}
        </span>
      )}
    </FilterPill>
  );
}
