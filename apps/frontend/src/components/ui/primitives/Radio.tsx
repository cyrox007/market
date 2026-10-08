import { createContext, useContext, useId } from 'react';
import type { ReactNode } from 'react';
import { cn } from '../../../lib/cn';

interface RadioGroupContextValue {
  name: string;
  value: string;
  onChange: (value: string) => void;
}

const RadioGroupContext = createContext<RadioGroupContextValue | null>(null);

interface RadioGroupProps {
  /** Название группы для читалки, например «Сортировка» */
  label: string;
  value: string;
  onChange: (value: string) => void;
  /** По умолчанию генерируется: имя нужно, чтобы стрелки ходили по пунктам */
  name?: string;
  className?: string;
  children: ReactNode;
}

/** Список вариантов с одним выбранным — сортировка. Замеры — market-docs/45. */
export function RadioGroup({ label, value, onChange, name, className, children }: RadioGroupProps) {
  const autoName = useId();

  return (
    <RadioGroupContext.Provider value={{ name: name ?? autoName, value, onChange }}>
      <div role="radiogroup" aria-label={label} className={cn('flex flex-col', className)}>
        {children}
      </div>
    </RadioGroupContext.Provider>
  );
}

interface RadioProps {
  value: string;
  label: ReactNode;
  disabled?: boolean;
  className?: string;
}

/** Пункт: подпись слева, кружок справа, выбранный — на сером фоне с жёлтой точкой */
export function Radio({ value, label, disabled = false, className }: RadioProps) {
  const group = useContext(RadioGroupContext);
  if (!group) throw new Error('Radio должен стоять внутри RadioGroup');

  const checked = group.value === value;

  return (
    <label
      className={cn(
        'flex h-11 items-center justify-between gap-2.5 rounded-btn px-4 text-14',
        disabled
          ? 'cursor-not-allowed text-ink-secondary'
          : cn('cursor-pointer text-ink', !checked && 'hover:bg-surface-grey'),
        checked && 'bg-surface-grey',
        'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-green',
        className,
      )}
    >
      <input
        type="radio"
        name={group.name}
        value={value}
        checked={checked}
        disabled={disabled}
        onChange={() => group.onChange(value)}
        className="sr-only"
      />
      <span className="min-w-0">{label}</span>
      <span
        aria-hidden="true"
        className={cn(
          'inline-flex size-5 shrink-0 items-center justify-center rounded-full border',
          checked ? 'border-brand-yellow' : 'border-surface-border',
          disabled ? 'bg-surface-grey' : 'bg-surface',
        )}
      >
        {checked && <span className="size-2.5 rounded-full bg-brand-yellow" />}
      </span>
    </label>
  );
}
