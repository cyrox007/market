import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '../../../lib/cn';

export type BadgeVariant = 'sale' | 'new' | 'info' | 'soft' | 'neutral';
export type BadgeSize = 'xs' | 'sm' | 'md' | 'lg';
export type BadgeShape = 'badge' | 'pill';

interface BadgeOwnProps {
  variant?: BadgeVariant;
  size?: BadgeSize;
  shape?: BadgeShape;
  icon?: ReactNode;
  className?: string;
  children: ReactNode;
}

type BadgeProps = BadgeOwnProps & Omit<HTMLAttributes<HTMLSpanElement>, keyof BadgeOwnProps>;

const VARIANT: Record<BadgeVariant, string> = {
  sale: 'bg-brand-red text-ink-inverse', // «Сезонная распродажа до −60%»
  new: 'bg-brand-yellow text-ink', // «Новинка», «Выгодно», «Гарантия»
  info: 'bg-brand-green text-ink-inverse', // «Уточняйте у менеджера»
  soft: 'bg-brand-red/10 text-brand-red', // «до −60%» в навигации
  neutral: 'bg-surface-grey text-ink-secondary',
};

/**
 * Шкала по возрастанию вертикального паддинга; `md` и `lg` ниже 549 сжимаются до `sm`.
 * ⚠️ Кегли `xs` и `sm` не замерены. Замеры — market-docs/09-ui-primitives-stage-1.md.
 */
const SIZE: Record<BadgeSize, string> = {
  xs: 'px-2.5 py-[5px] text-10',
  sm: 'px-3 py-1.5 text-12',
  md: 'px-3 py-2 text-12 max-vsm:py-1.5',
  lg: 'px-4 py-2 text-12 max-vsm:px-3 max-vsm:py-1.5',
};

const SHAPE: Record<BadgeShape, string> = {
  badge: 'rounded-badge', // только «до −60%» в навигации
  pill: 'rounded-pill',
};

export default function Badge({
  variant = 'new',
  size = 'md',
  shape = 'pill',
  icon,
  className,
  children,
  ...rest
}: BadgeProps) {
  return (
    <span
      {...rest}
      className={cn(
        // Регистр задаётся текстом, а не компонентом: в макете есть и капс, и обычный
        'inline-flex items-center gap-1 font-medium whitespace-nowrap',
        VARIANT[variant],
        SIZE[size],
        SHAPE[shape],
        className,
      )}
    >
      {icon && <span className="inline-flex shrink-0">{icon}</span>}
      {children}
    </span>
  );
}
