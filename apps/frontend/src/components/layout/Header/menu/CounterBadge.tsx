import { cn } from '../../../../lib/cn';

interface CounterBadgeProps {
  count: number;
  /** Выше этого показываем «99+» — так уже сделано в нынешней шапке */
  max?: number;
  className?: string;
}

/** Счётчик на иконке. ⚠️ Размеры на глаз, не замерены — market-docs/13-header.md */
export default function CounterBadge({ count, max = 99, className }: CounterBadgeProps) {
  if (count <= 0) return null;

  return (
    <span
      aria-hidden="true"
      className={cn(
        'pointer-events-none absolute -right-1 -top-1 inline-flex size-4 items-center justify-center',
        'rounded-full bg-brand-red text-10 font-medium text-ink-inverse',
        className,
      )}
    >
      {count > max ? `${max}+` : count}
    </span>
  );
}
