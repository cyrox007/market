import { useId } from 'react';
import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '../../../lib/cn';

interface ToggleOwnProps {
  label: ReactNode;
  checked: boolean;
  onChange: (checked: boolean) => void;
  className?: string;
}

type ToggleProps = ToggleOwnProps &
  Omit<ButtonHTMLAttributes<HTMLButtonElement>, keyof ToggleOwnProps | 'type' | 'role'>;

/**
 * Переключатель «вкл / выкл»: подпись слева, тумблер справа.
 * В UI Kit его нет — размеры взяты по аналогии (market-docs/45, «Допущения»).
 */
export default function Toggle({
  label,
  checked,
  onChange,
  disabled,
  className,
  ...rest
}: ToggleProps) {
  const labelId = useId();

  return (
    <div
      className={cn(
        'flex items-center justify-between gap-2.5 text-14',
        disabled ? 'text-ink-secondary' : 'text-ink',
        className,
      )}
    >
      <span id={labelId} className="min-w-0">
        {label}
      </span>
      <button
        type="button"
        role="switch"
        {...rest}
        aria-checked={checked}
        aria-labelledby={labelId}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={cn(
          'relative inline-flex h-6 w-10 shrink-0 items-center rounded-pill p-0.5',
          'outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2',
          disabled
            ? 'cursor-not-allowed bg-surface-grey'
            : cn('cursor-pointer', checked ? 'bg-brand-yellow' : 'bg-surface-border'),
        )}
      >
        <span
          aria-hidden="true"
          className={cn(
            'size-5 rounded-full bg-surface shadow-btn',
            checked ? 'translate-x-4' : 'translate-x-0',
          )}
        />
      </button>
    </div>
  );
}
