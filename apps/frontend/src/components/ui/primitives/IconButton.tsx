import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '../../../lib/cn';

export type IconButtonVariant = 'white' | 'yellow' | 'grey' | 'danger';
export type IconButtonSize = 'sm' | 'md' | 'lg';

interface IconButtonOwnProps {
  /** Обязательно: у кнопки нет текста, читалке нужно название действия */
  label: string;
  variant?: IconButtonVariant;
  size?: IconButtonSize;
  /** Нажатое состояние — например, товар уже в избранном */
  isActive?: boolean;
  elevated?: boolean;
  className?: string;
  children: ReactNode;
}

type IconButtonProps = IconButtonOwnProps &
  Omit<ButtonHTMLAttributes<HTMLButtonElement>, keyof IconButtonOwnProps>;

/**
 * Замерено с макета 07.09.2026:
 *   sm 36 — жёлтая кнопка внутри поля поиска, иконка 20
 *   md 40 — свёрнутые «Каталог» и «Комнаты» ниже 980, иконка 20
 *   lg 48 — ⚠️ не замерен, оставлен как был
 *
 * Раньше шкала была 32 / 40 / 48 и целиком выведена. 32 убран: ни одного
 * подтверждения ему в макете не нашлось, а 36 подтверждён.
 */
const SIZE: Record<IconButtonSize, string> = {
  sm: 'size-9',
  md: 'size-10',
  lg: 'size-12',
};

const VARIANT: Record<IconButtonVariant, string> = {
  white: 'bg-surface text-ink hover:bg-surface-grey',
  yellow: 'bg-brand-yellow text-ink hover:bg-brand-green hover:text-ink-inverse',
  grey: 'bg-surface-grey text-ink hover:bg-surface-border',
  danger: 'bg-brand-red text-ink-inverse hover:opacity-90',
};

/** Нажатое состояние: у белой кнопки становится красным, у остальных — зелёным */
const ACTIVE: Record<IconButtonVariant, string> = {
  white: 'bg-brand-red text-ink-inverse hover:bg-brand-red',
  yellow: 'bg-brand-green text-ink-inverse',
  grey: 'bg-brand-green text-ink-inverse',
  danger: 'bg-brand-red text-ink-inverse',
};

/**
 * Круглая иконочная кнопка.
 *
 * Цвет меняется мгновенно, без перехода — дизайнер подтвердил 07.09.2026, что
 * движения в макете не предполагалось. Чтобы вернуть плавность, достаточно
 * передать className="transition-colors duration-fast", токены длительностей
 * в конфиге остались.
 */
export default function IconButton({
  label,
  variant = 'white',
  size = 'md',
  isActive = false,
  elevated = false,
  className,
  children,
  disabled,
  ...rest
}: IconButtonProps) {
  return (
    <button
      type="button"
      {...rest}
      aria-label={label}
      aria-pressed={isActive || undefined}
      disabled={disabled}
      className={cn(
        'inline-flex shrink-0 items-center justify-center rounded-full',
        'outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2',
        SIZE[size],
        disabled
          ? 'cursor-not-allowed bg-surface-grey text-ink-secondary'
          : cn('cursor-pointer', isActive ? ACTIVE[variant] : VARIANT[variant]),
        // shadow-btn — замеренная тень круглых кнопок на карточке товара,
        // не путать с shadow-card: та крупнее и стоит под самой карточкой
        elevated && 'shadow-btn',
        className,
      )}
    >
      {children}
    </button>
  );
}
