import { forwardRef } from 'react';
import type { InputHTMLAttributes, ReactNode } from 'react';
import { cn } from '../../../lib/cn';

export type InputSize = 'sm' | 'md' | 'lg';
export type InputShape = 'pill' | 'rounded';

interface InputOwnProps {
  inputSize?: InputSize;
  shape?: InputShape;
  /** Тон подложки: серая как в шапке или белая с рамкой как в формах */
  tone?: 'grey' | 'white';
  leftIcon?: ReactNode;
  /** Слот справа внутри поля — кнопка отправки, счётчик, крестик очистки */
  rightSlot?: ReactNode;
  invalid?: boolean;
  className?: string;
  wrapperClassName?: string;
}

type InputProps = InputOwnProps &
  Omit<InputHTMLAttributes<HTMLInputElement>, keyof InputOwnProps | 'size'>;

/**
 * Высота задана на обёртке, а не паддингом внутри: иначе рамка 1 px прибавляется
 * сверху и снизу, и поле `md` оказывается на 2 px выше кнопки `md` — в форме,
 * где они стоят рядом, это видно.
 *
 * `md` замерен с макета на поиске в шапке (07.09.2026): высота 44, кегль 14,
 * строка 17, вертикальный паддинг 13.5 — то есть 13.5 + 17 + 13.5 = 44, и здесь
 * высота тоже складывается из содержимого, а не задаётся паддингом напрямую.
 *
 * ⚠️ `sm` и `lg` НЕ замерены, выровнены по кнопке. Других полей в макете пока
 * не смотрели — при сверке проверить первыми.
 */
const HEIGHT: Record<InputSize, string> = {
  sm: 'h-9',
  md: 'h-11',
  lg: 'h-[52px]',
};

const TEXT: Record<InputSize, string> = {
  sm: 'text-14',
  md: 'text-14',
  lg: 'text-16',
};

const SHAPE: Record<InputShape, string> = {
  pill: 'rounded-pill',
  rounded: 'rounded-btn',
};

/**
 * Текстовое поле. Рамка меняет цвет мгновенно, как и остальные примитивы —
 * дизайнер подтвердил 07.09.2026, что движения не предполагалось.
 * Вернуть плавность: wrapperClassName="transition-colors duration-fast".
 */
const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  {
    inputSize = 'md',
    shape = 'pill',
    tone = 'grey',
    leftIcon,
    rightSlot,
    invalid = false,
    className,
    wrapperClassName,
    disabled,
    ...rest
  },
  ref,
) {
  return (
    <div
      className={cn(
        'relative inline-flex w-full items-center border',
        HEIGHT[inputSize],
        SHAPE[shape],
        tone === 'grey' ? 'bg-surface-grey' : 'bg-surface',
        // Замерено с макета 07.09.2026: в покое рамка #F5F3F1 (surface-grey),
        // в фокусе #E8E5E1 (surface-border). Обе светлые — тёмной рамки, которая
        // мерещилась на скриншоте, в макете нет, это была рамка выделения Figma.
        invalid
          ? 'border-brand-red'
          : cn('border-surface-grey', !disabled && 'focus-within:border-surface-border'),
        disabled && 'opacity-60',
        wrapperClassName,
      )}
    >
      {leftIcon && <span className="inline-flex shrink-0 pl-4 text-ink-secondary">{leftIcon}</span>}
      <input
        ref={ref}
        {...rest}
        disabled={disabled}
        aria-invalid={invalid || undefined}
        className={cn(
          'min-w-0 h-full flex-1 bg-transparent px-4 text-ink outline-none',
          'placeholder:text-ink-secondary',
          'disabled:cursor-not-allowed',
          TEXT[inputSize],
          className,
        )}
      />
      {/* 4 = (44 − 36) / 2: круглая кнопка внутри поля отбита поровну со всех сторон */}
      {rightSlot && <span className="inline-flex shrink-0 pr-1">{rightSlot}</span>}
    </div>
  );
});

export default Input;
