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
 * Высота на обёртке, а не паддингом: иначе рамка прибавляется сверху и снизу,
 * и поле `md` выходит на 2 px выше кнопки `md`.
 * ⚠️ `sm` и `lg` не замерены. Замеры — market-docs/09-ui-primitives-stage-1.md.
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
        'transition-colors motion-reduce:transition-none',
        HEIGHT[inputSize],
        SHAPE[shape],
        tone === 'grey' ? 'bg-surface-grey' : 'bg-surface',
        // Обе рамки светлые: тёмной, которая мерещилась на скриншоте, в макете нет
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
      {/* Круглая кнопка внутри поля отбита поровну со всех сторон */}
      {rightSlot && <span className="inline-flex shrink-0 pr-1">{rightSlot}</span>}
    </div>
  );
});

export default Input;
