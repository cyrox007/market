import { cn } from '../../../../lib/cn';

interface OnSalePillProps {
  checked: boolean;
  onChange: (checked: boolean) => void;
}

/** «По акции» в строке: тумблер слева, применяется сразу (market-docs/44) */
export default function OnSalePill({ checked, onChange }: OnSalePillProps) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      onClick={() => onChange(!checked)}
      className={cn(
        'inline-flex h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-pill border border-surface-border px-4 text-16 text-ink',
        'outline-none transition-colors hover:border-ink-secondary focus-visible:ring-2 focus-visible:ring-brand-green',
      )}
    >
      <span
        aria-hidden="true"
        className={cn(
          'inline-flex h-6 w-10 shrink-0 items-center rounded-pill p-0.5',
          checked ? 'bg-brand-yellow' : 'bg-surface-border',
        )}
      >
        <span
          className={cn(
            'size-5 rounded-full bg-surface shadow-btn',
            checked ? 'translate-x-4' : 'translate-x-0',
          )}
        />
      </span>
      По акции
    </button>
  );
}
