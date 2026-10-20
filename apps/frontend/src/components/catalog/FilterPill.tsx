import type { ButtonHTMLAttributes, ReactNode, Ref } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '../../lib/cn';

interface FilterPillProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  ref?: Ref<HTMLButtonElement>;
  /** Окно открыто — вид как при наведении, стрелка вверх */
  open?: boolean;
  /** Стрелка у кнопок с выпадающим окном */
  chevron?: boolean;
  icon?: ReactNode;
  /** Ниже 980 — текстом без рамки и фона (макеты 768 / 375), наведение — серый текст */
  textBelowMd?: boolean;
}

/**
 * Кнопка строки фильтров (Figma 721:46062): рамка Surface/Border, 14 / 10, 16 Medium.
 * Наведение и открытое окно — серый фон, жёлтый текст и иконка.
 */
export default function FilterPill({
  ref,
  open = false,
  chevron = true,
  icon,
  textBelowMd = false,
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
        'inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-pill border border-surface-border px-3.5 py-2.5 text-16 font-medium',
        'outline-none transition-colors hover:bg-surface-grey hover:text-brand-yellow focus-visible:ring-2 focus-visible:ring-brand-green',
        open ? 'bg-surface-grey text-brand-yellow' : 'bg-surface text-ink',
        textBelowMd &&
          'max-md:border-transparent max-md:bg-transparent max-md:px-0 max-md:text-ink max-md:hover:bg-transparent max-md:hover:text-ink-secondary',
        className,
      )}
    >
      {icon}
      {children}
      {chevron && (
        <ChevronDown
          aria-hidden
          className={cn(
            'size-4 shrink-0 transition-transform duration-300 ease-out motion-reduce:transition-none',
            open && 'rotate-180',
          )}
        />
      )}
    </button>
  );
}
