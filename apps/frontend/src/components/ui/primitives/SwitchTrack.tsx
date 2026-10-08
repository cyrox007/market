import { cn } from '../../../lib/cn';

interface SwitchTrackProps {
  checked: boolean;
  disabled?: boolean;
}

/** Вид тумблера без поведения: 32×18, кружок 14 (Figma 721:46077). Кнопку с role="switch" ставит владелец */
export default function SwitchTrack({ checked, disabled = false }: SwitchTrackProps) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        'inline-flex h-[18px] w-8 shrink-0 items-center rounded-pill p-0.5',
        disabled ? 'bg-surface-grey' : checked ? 'bg-brand-yellow' : 'bg-surface-border',
      )}
    >
      <span
        className={cn(
          'size-3.5 rounded-full bg-surface shadow-btn',
          checked ? 'translate-x-3.5' : 'translate-x-0',
        )}
      />
    </span>
  );
}
