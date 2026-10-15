import { Filter } from 'lucide-react';
import { useState } from 'react';
import { cn } from '../../../../lib/cn';
import FilterPill from './FilterPill';

interface AllFiltersButtonProps {
  count: number;
  onClick: () => void;
}

/** «Фильтры» с числом выбранных — открывает панель «Все фильтры» (задача 04) */
export default function AllFiltersButton({ count, onClick }: AllFiltersButtonProps) {
  // Число уходит вместе с местом под него — показываем последнее, пока ширина схлопывается
  const [shown, setShown] = useState(count);
  if (count > 0 && count !== shown) setShown(count);
  const open = count > 0;

  return (
    <FilterPill
      chevron={false}
      textBelowMd
      icon={<Filter aria-hidden className="size-4 shrink-0" />}
      aria-label={count ? `Фильтры, выбрано ${count}` : 'Фильтры'}
      onClick={onClick}
    >
      Фильтры
      {/* Место под число раскрывается по ширине (0fr → 1fr), соседние кнопки съезжают плавно;
          -ml-2 гасит gap кнопки, пока числа нет */}
      <span
        aria-hidden="true"
        className={cn(
          'grid transition-[grid-template-columns,margin,opacity] duration-slow ease-out motion-reduce:transition-none',
          open ? 'ml-0 grid-cols-[1fr] opacity-100' : '-ml-2 grid-cols-[0fr] opacity-0',
        )}
      >
        <span className="min-w-0 overflow-hidden">
          {shown > 0 && (
            <span
              // Новое число — заново мягко появляется
              key={shown}
              className="inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-pill bg-brand-yellow px-1 text-12 font-semibold text-ink motion-safe:animate-fadeIn max-md:bg-ink max-md:text-ink-inverse"
            >
              {shown}
            </span>
          )}
        </span>
      </span>
    </FilterPill>
  );
}
