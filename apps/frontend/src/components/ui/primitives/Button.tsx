import { Link } from 'react-router-dom';
import type { AnchorHTMLAttributes, ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '../../../lib/cn';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost';
export type ButtonSize = 'sm' | 'md' | 'lg';
export type ButtonShape = 'pill' | 'rounded';

/** `none` — принятый вариант. Остальное — заготовка заливки, market-docs/09. */
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
    to?: string;
    href?: string;
    target?: AnchorHTMLAttributes<HTMLAnchorElement>['target'];
    rel?: AnchorHTMLAttributes<HTMLAnchorElement>['rel'];
  };

/**
 * Высота задана явно, а не паддингом: при auto к ней прибавляются рамка и то,
 * что решил шрифт, и `outline` выходил на 2 px выше `primary`.
 * Замеры и обоснование размеров — market-docs/09-ui-primitives-stage-1.md.
 */
const SIZE: Record<ButtonSize, string> = {
  sm: 'h-11 px-6 text-14',
  md: 'h-11 px-6 text-16',
  lg: 'h-[52px] px-6 text-16',
};

/** Промежуток привязан к размеру, потому что так легли замеры (market-docs/09). */
const GAP: Record<ButtonSize, string> = {
  sm: 'gap-2',
  md: 'gap-1',
  lg: 'gap-1',
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

const VARIANT_HOVER: Record<ButtonVariant, string> = {
  primary: 'hover:bg-brand-green hover:text-ink-inverse',
  secondary: 'hover:bg-brand-green hover:text-ink-inverse',
  outline: 'hover:border-brand-green hover:bg-brand-green hover:text-ink-inverse',
  ghost: 'hover:text-brand-green',
};

/** То же для клавиатуры: иначе пользователь Tab не видит отклика. */
const VARIANT_FOCUS: Record<ButtonVariant, string> = {
  primary: 'focus-visible:bg-brand-green focus-visible:text-ink-inverse',
  secondary: 'focus-visible:bg-brand-green focus-visible:text-ink-inverse',
  outline:
    'focus-visible:border-brand-green focus-visible:bg-brand-green focus-visible:text-ink-inverse',
  ghost: 'focus-visible:text-brand-green',
};

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
  const hasFill = fill !== 'none' && variant !== 'ghost' && !isDisabled;

  const root = cn(
    'group relative inline-flex items-center justify-center overflow-hidden',
    'font-medium whitespace-nowrap select-none',
    'outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2',
    'transition-colors motion-reduce:transition-none',
    SIZE[size],
    SHAPE[shape],
    isDisabled
      ? 'cursor-not-allowed bg-surface-grey text-ink-secondary border-transparent'
      : cn(VARIANT[variant], 'cursor-pointer'),
    !isDisabled && !hasFill && cn(VARIANT_HOVER[variant], VARIANT_FOCUS[variant]),
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
          GAP[size],
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
