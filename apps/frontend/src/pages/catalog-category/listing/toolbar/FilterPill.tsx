import type { ButtonHTMLAttributes, ReactNode, Ref } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '../../../../lib/cn';

interface FilterPillProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  ref?: Ref<HTMLButtonElement>;
  /** Окно открыто — стрелка вверх */
  open?: boolean;
  /** Стрелка у кнопок с выпадающим окном */
  chevron?: boolean;
  icon?: ReactNode;
}

/** Кнопка строки фильтров: «Цена», «Габариты», сортировка, «Фильтры» */
export default function FilterPill({
  ref,
  open = false,
  chevron = true,
  icon,
  className,
  children,
  ...rest
}: FilterPillProps) {
  return (
    <button
      ref={ref}
      type="button"
      aria-expanded={chevron ? open : undefined}
      {...rest}
      className={cn(
        'inline-flex h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-pill border px-4 text-16 text-ink',
        'outline-none transition-colors hover:border-ink-secondary focus-visible:ring-2 focus-visible:ring-brand-green',
        open ? 'border-ink-secondary' : 'border-surface-border',
        className,
      )}
    >
      {icon}
      {children}
      {chevron && (
        <ChevronDown aria-hidden className={cn('size-5 shrink-0', open && 'rotate-180')} />
      )}
    </button>
  );
}
