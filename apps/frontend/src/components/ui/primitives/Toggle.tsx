import { useId } from 'react';
import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '../../../lib/cn';
import SwitchTrack from './SwitchTrack';

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
 * Размеры — Figma 721:46077 (market-docs/45).
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
          'shrink-0 rounded-pill outline-none',
          'focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2',
          disabled ? 'cursor-not-allowed' : 'cursor-pointer',
        )}
      >
        <SwitchTrack checked={checked} disabled={disabled} />
      </button>
    </div>
  );
}
