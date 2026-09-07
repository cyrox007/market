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

const SIZE: Record<InputSize, string> = {
  sm: 'py-2 text-14',
  md: 'py-3 text-16',
  lg: 'py-4 text-16',
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
        SHAPE[shape],
        tone === 'grey' ? 'bg-surface-grey' : 'bg-surface',
        invalid
          ? 'border-brand-red'
          : cn(
              tone === 'grey' ? 'border-transparent' : 'border-surface-border',
              !disabled && 'focus-within:border-brand-green',
            ),
        disabled && 'opacity-60',
        wrapperClassName,
      )}
    >
      {leftIcon && (
        <span className="inline-flex shrink-0 pl-4 text-ink-secondary">{leftIcon}</span>
      )}
      <input
        ref={ref}
        {...rest}
        disabled={disabled}
        aria-invalid={invalid || undefined}
        className={cn(
          'min-w-0 flex-1 bg-transparent px-4 text-ink outline-none',
          'placeholder:text-ink-secondary',
          'disabled:cursor-not-allowed',
          SIZE[inputSize],
          className,
        )}
      />
      {rightSlot && <span className="inline-flex shrink-0 pr-1.5">{rightSlot}</span>}
    </div>
  );
});

export default Input;
