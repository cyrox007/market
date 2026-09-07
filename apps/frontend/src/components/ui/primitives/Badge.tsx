import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '../../../lib/cn';

export type BadgeVariant = 'sale' | 'new' | 'info' | 'soft' | 'neutral';
export type BadgeSize = 'sm' | 'md';
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

const SIZE: Record<BadgeSize, string> = {
  sm: 'px-2 py-1 text-10',
  md: 'px-3 py-1 text-12',
};

const SHAPE: Record<BadgeShape, string> = {
  badge: 'rounded-badge',
  pill: 'rounded-pill',
};

export default function Badge({
  variant = 'new',
  size = 'md',
  shape = 'badge',
  icon,
  className,
  children,
  ...rest
}: BadgeProps) {
  return (
    <span
      {...rest}
      className={cn(
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
