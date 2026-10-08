import type { InputHTMLAttributes, ReactNode } from 'react';
import { Check } from 'lucide-react';
import { cn } from '../../../lib/cn';

interface CheckboxOwnProps {
  label: ReactNode;
  checked: boolean;
  onChange: (checked: boolean) => void;
  className?: string;
}

type CheckboxProps = CheckboxOwnProps &
  Omit<InputHTMLAttributes<HTMLInputElement>, keyof CheckboxOwnProps | 'type'>;

/**
 * Галочка с подписью справа. Под квадратом настоящий input — клавиатура и читалка
 * работают как у обычного чекбокса. Замеры и состояния — market-docs/45.
 */
export default function Checkbox({
  label,
  checked,
  onChange,
  disabled,
  className,
  ...rest
}: CheckboxProps) {
  return (
    <label
      className={cn(
        'inline-flex items-center gap-2.5 text-14',
        disabled ? 'cursor-not-allowed text-ink-secondary' : 'cursor-pointer text-ink',
        className,
      )}
    >
      <input
        type="checkbox"
        {...rest}
        checked={checked}
        disabled={disabled}
        onChange={(event) => onChange(event.target.checked)}
        className="peer sr-only"
      />
      <span
        aria-hidden="true"
        className={cn(
          'inline-flex size-5 shrink-0 items-center justify-center rounded-check border',
          'peer-focus-visible:ring-2 peer-focus-visible:ring-brand-green peer-focus-visible:ring-offset-2',
          disabled
            ? 'border-surface-border bg-surface-grey'
            : checked
              ? 'border-brand-yellow bg-brand-yellow text-ink'
              : 'border-surface-border bg-surface hover:border-ink-secondary',
        )}
      >
        {checked && <Check className="size-4" strokeWidth={2.5} />}
      </span>
      <span className="min-w-0 flex-1">{label}</span>
    </label>
  );
}
