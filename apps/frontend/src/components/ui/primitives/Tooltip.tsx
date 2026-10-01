import type { ReactNode } from 'react';
import { cn } from '../../../lib/cn';

interface TooltipProps {
  /** Только текст — для богатого содержимого этот тултип не задуман */
  text: string;
  children: ReactNode;
  /**
   * Выравнивать по ближайшему позиционированному предку, а не по самому элементу.
   * Нужно, когда кнопка стоит не у края и подсказка вылезла бы за обрезку контейнера
   */
  alignToParent?: boolean;
  className?: string;
}

/**
 * Текстовая подсказка под элементом, по правому краю. Без JS: наведение мышью
 * или фокус с клавиатуры. На тач-устройствах не показывается.
 * Подпись для читалки должна быть у самого элемента — тултип от неё скрыт.
 * Замеры — market-docs/28-tooltip.md
 */
export default function Tooltip({ text, children, alignToParent = false, className }: TooltipProps) {
  return (
    <span className={cn('group/tip inline-flex', !alignToParent && 'relative', className)}>
      {children}
      <span
        aria-hidden="true"
        className={cn(
          'pointer-events-none absolute right-0 top-full z-10 mt-2 whitespace-nowrap',
          'rounded-lg bg-brand-green px-3 py-1.5 text-12 font-semibold leading-none text-ink-inverse',
          'translate-y-1 opacity-0 transition duration-150 motion-reduce:transition-none',
          '[@media(hover:hover)]:group-hover/tip:translate-y-0 [@media(hover:hover)]:group-hover/tip:opacity-100',
          'group-has-[:focus-visible]/tip:translate-y-0 group-has-[:focus-visible]/tip:opacity-100',
        )}
      >
        {text}
      </span>
    </span>
  );
}
