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
 * Замерено с макета 07.09.2026. Шкала по возрастанию вертикального паддинга:
 *
 *   xs  «до −60%» в навигации           — 5 / 10, радиус 6
 *   sm  бейджи на карточках товара      — 6 / 12, пилюля, цвета разные
 *   md  «Выгодно», «Гарантия»           — 8 / 12, пилюля
 *   lg  «Сезонная распродажа до −60%»   — 8 / 16, пилюля
 *
 * `md` и `lg` ниже 549 сжимаются до 6 / 12, то есть до `sm` по паддингу —
 * вариант `max-vsm`, брейкпоинт заведён в tailwind.config.ts. Почему пользуемся
 * `max-*`, а не `vsm:` напрямую, написано там же в комментарии к `screens`.
 *
 * ⚠️ Кегли замерены только у `md` и `lg` (12). У `xs` и `sm` в макете не смотрели,
 * оставлены 10 и 12 как рабочие.
 */
const SIZE: Record<BadgeSize, string> = {
  xs: 'px-2.5 py-[5px] text-10',
  sm: 'px-3 py-1.5 text-12',
  md: 'px-3 py-2 text-12 max-vsm:py-1.5',
  lg: 'px-4 py-2 text-12 max-vsm:px-3 max-vsm:py-1.5',
};

/**
 * По умолчанию пилюля: замеры показали радиус 100 у всех бейджей, кроме
 * навигационного. Раньше по умолчанию стоял `badge`, и «Выгодно» с «Гарантией»
 * рисовались почти прямоугольными.
 */
const SHAPE: Record<BadgeShape, string> = {
  badge: 'rounded-badge', // 6, только «до −60%» в навигации
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
        // Регистр НЕ навязываем. Первая версия переводила текст в заглавные —
        // на трёх верхних карточках главной они действительно капсом. Но на
        // карточках товара «Новинка» и «Уточняйте у менеджера» набраны обычными,
        // то есть регистр задаётся текстом, а не компонентом.
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
