import { Link } from 'react-router-dom';
import type { AnchorHTMLAttributes, ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '../../../lib/cn';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost';
export type ButtonSize = 'sm' | 'md' | 'lg';
export type ButtonShape = 'pill' | 'rounded';

/**
 * Поведение при наведении.
 *
 * `none` — принятый вариант: цвет меняется мгновенно, без перехода.
 * Дизайнер подтвердил 07.09.2026, что движение не предполагалось вовсе,
 * в макете нарисованы просто два состояния.
 *
 * Остальные три — заготовка заливки на случай, если решение поменяется.
 * Разбор, почему заливка, а не анимация цвета: market-docs/04-button-hover-motion.md.
 * Включается одним пропом, править компонент не нужно.
 */
export type ButtonFill = 'none' | 'up' | 'left' | 'center';

interface ButtonOwnProps {
  variant?: ButtonVariant;
  size?: ButtonSize;
  shape?: ButtonShape;
  fill?: ButtonFill;
  fullWidth?: boolean;
  isLoading?: boolean;
  leftIcon?: ReactNode;
  rightIcon?: ReactNode;
  className?: string;
  children?: ReactNode;
}

type ButtonProps = ButtonOwnProps &
  Omit<ButtonHTMLAttributes<HTMLButtonElement>, keyof ButtonOwnProps> & {
    /** Внутренний переход — рендерится react-router Link */
    to?: string;
    /** Внешняя ссылка — рендерится <a> */
    href?: string;
    target?: AnchorHTMLAttributes<HTMLAnchorElement>['target'];
    rel?: AnchorHTMLAttributes<HTMLAnchorElement>['rel'];
  };

const SIZE: Record<ButtonSize, string> = {
  // Шкала макета кратна четырём: 4, 8, 12, 16, 20, 28 (market-docs/01-base-font-size.md)
  sm: 'px-4 py-2 text-14 gap-2',
  md: 'px-5 py-3 text-16 gap-2',
  lg: 'px-7 py-4 text-16 gap-3',
};

const SHAPE: Record<ButtonShape, string> = {
  pill: 'rounded-pill',
  rounded: 'rounded-btn',
};

const VARIANT: Record<ButtonVariant, string> = {
  primary: 'bg-brand-yellow text-ink',
  secondary: 'bg-surface-grey text-ink',
  outline: 'border border-surface-border bg-transparent text-ink',
  ghost: 'bg-transparent text-ink',
};

/** Мгновенная смена цвета — состояние hover из макета, без перехода между ними. */
const VARIANT_HOVER: Record<ButtonVariant, string> = {
  primary: 'hover:bg-brand-green hover:text-ink-inverse',
  secondary: 'hover:bg-brand-green hover:text-ink-inverse',
  outline: 'hover:border-brand-green hover:bg-brand-green hover:text-ink-inverse',
  ghost: 'hover:text-brand-green',
};

/** То же самое для клавиатуры: иначе пользователь Tab не видит отклика. */
const VARIANT_FOCUS: Record<ButtonVariant, string> = {
  primary: 'focus-visible:bg-brand-green focus-visible:text-ink-inverse',
  secondary: 'focus-visible:bg-brand-green focus-visible:text-ink-inverse',
  outline:
    'focus-visible:border-brand-green focus-visible:bg-brand-green focus-visible:text-ink-inverse',
  ghost: 'focus-visible:text-brand-green',
};

/* --- Заготовка заливки. Не участвует при fill="none". --- */

const FILL_FROM: Record<Exclude<ButtonFill, 'none'>, string> = {
  up: 'translate-y-full',
  left: '-translate-x-full',
  center: 'scale-y-0',
};

const FILL_TO: Record<Exclude<ButtonFill, 'none'>, string> = {
  up: 'group-hover:translate-y-0 group-focus-visible:translate-y-0',
  left: 'group-hover:translate-x-0 group-focus-visible:translate-x-0',
  center: 'group-hover:scale-y-100 group-focus-visible:scale-y-100',
};

export default function Button({
  variant = 'primary',
  size = 'md',
  shape = 'pill',
  fill = 'none',
  fullWidth = false,
  isLoading = false,
  leftIcon,
  rightIcon,
  className,
  children,
  to,
  href,
  disabled,
  ...rest
}: ButtonProps) {
  const isDisabled = disabled || isLoading;

  // Заливка неуместна у ghost (это по сути ссылка) и в выключенном состоянии
  const hasFill = fill !== 'none' && variant !== 'ghost' && !isDisabled;

  const root = cn(
    'group relative inline-flex items-center justify-center overflow-hidden',
    'font-medium whitespace-nowrap select-none',
    'outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2',
    SIZE[size],
    SHAPE[shape],
    // Выключенная кнопка по макету серая и без тени
    isDisabled
      ? 'cursor-not-allowed bg-surface-grey text-ink-secondary border-transparent'
      : cn(VARIANT[variant], 'cursor-pointer'),
    // Смена цвета мгновенная. Чтобы вернуть плавность, достаточно добавить
    // className="transition-colors" в месте использования.
    !isDisabled && !hasFill && cn(VARIANT_HOVER[variant], VARIANT_FOCUS[variant]),
    // При включённой заливке цвет фона не трогаем — его закрывает слой
    hasFill && 'motion-reduce:hover:bg-brand-green motion-reduce:hover:text-ink-inverse',
    fullWidth && 'w-full',
    className,
  );

  const content = (
    <>
      {hasFill && (
        <span
          aria-hidden="true"
          className={cn(
            'absolute inset-0 bg-brand-green ease-out',
            'transition-transform duration-slow',
            fill === 'center' && 'origin-center',
            FILL_FROM[fill],
            FILL_TO[fill],
            'motion-reduce:hidden',
          )}
        />
      )}
      <span
        className={cn(
          'relative inline-flex items-center justify-center',
          SIZE[size].includes('gap-3') ? 'gap-3' : 'gap-2',
          hasFill &&
            'transition-colors duration-fast delay-100 group-hover:text-ink-inverse group-focus-visible:text-ink-inverse',
          hasFill && 'motion-reduce:transition-none motion-reduce:delay-0',
        )}
      >
        {isLoading ? (
          <span
            aria-hidden="true"
            className="size-[1em] shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent"
          />
        ) : (
          leftIcon && <span className="inline-flex shrink-0">{leftIcon}</span>
        )}
        {children}
        {rightIcon && <span className="inline-flex shrink-0">{rightIcon}</span>}
      </span>
    </>
  );

  if (to && !isDisabled) {
    const { target, rel } = rest as AnchorHTMLAttributes<HTMLAnchorElement>;
    return (
      <Link to={to} className={root} target={target} rel={rel}>
        {content}
      </Link>
    );
  }

  if (href && !isDisabled) {
    const { target, rel } = rest as AnchorHTMLAttributes<HTMLAnchorElement>;
    return (
      <a href={href} className={root} target={target} rel={rel}>
        {content}
      </a>
    );
  }

  return (
    <button
      type="button"
      {...rest}
      className={root}
      disabled={isDisabled}
      aria-busy={isLoading || undefined}
    >
      {content}
    </button>
  );
}
