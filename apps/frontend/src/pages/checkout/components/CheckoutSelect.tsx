import { useEffect, useId, useRef, useState } from 'react';
import { ChevronDown } from 'lucide-react';

interface Option {
  value: string;
  label: string;
  disabled?: boolean;
}
interface Props {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: Option[];
  placeholder?: string;
}

/** Single-choice checkout field. The menu overlays the form rather than moving it. */
export default function CheckoutSelect({
  label,
  value,
  onChange,
  options,
  placeholder = 'Выберите вариант',
}: Props) {
  const id = useId();
  const root = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const selected = options.find((option) => option.value === value);
  function show() {
    setActive(options.findIndex((option) => option.value === value && !option.disabled));
    setOpen(true);
  }
  function choose(index: number) {
    const option = options[index];
    if (!option || option.disabled) return;
    onChange(option.value);
    setOpen(false);
    trigger.current?.focus({ preventScroll: true });
  }
  useEffect(() => {
    if (!open) return;
    const outside = (event: PointerEvent) => {
      if (!root.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener('pointerdown', outside);
    return () => document.removeEventListener('pointerdown', outside);
  }, [open]);
  useEffect(() => {
    if (open && active >= 0)
      document.getElementById(`${id}-${active}`)?.scrollIntoView?.({ block: 'nearest' });
  }, [open, active, id]);
  return (
    <div
      ref={root}
      className="relative min-w-0 space-y-2"
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) setOpen(false);
      }}
    >
      <label id={`${id}-label`} htmlFor={id} className="block text-14 text-ink-secondary">
        {label}
      </label>
      <button
        ref={trigger}
        id={id}
        type="button"
        role="combobox"
        aria-labelledby={`${id}-label`}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={`${id}-list`}
        aria-activedescendant={open && active >= 0 ? `${id}-${active}` : undefined}
        onClick={() => (open ? setOpen(false) : show())}
        onKeyDown={(event) => {
          if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
          } else if (event.key === 'Tab') setOpen(false);
          else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (!open) {
              show();
              return;
            }
            const enabled = options
              .map((option, index) => (option.disabled ? -1 : index))
              .filter((index) => index >= 0);
            if (!enabled.length) return;
            const position = enabled.indexOf(active);
            if (position < 0) {
              setActive(event.key === 'ArrowDown' ? enabled[0] : enabled[enabled.length - 1]);
              return;
            }
            setActive(
              enabled[
                (position + (event.key === 'ArrowDown' ? 1 : enabled.length - 1) + enabled.length) %
                  enabled.length
              ],
            );
          } else if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            setOpen(true);
            const enabled = options
              .map((option, index) => (option.disabled ? -1 : index))
              .filter((index) => index >= 0);
            setActive((event.key === 'Home' ? enabled[0] : enabled.at(-1)) ?? -1);
          } else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            if (open) choose(active);
            else show();
          }
        }}
        className={`flex min-h-[47px] w-full items-center justify-between gap-3 rounded-btn border bg-surface px-5 py-3 text-left text-14 outline-none focus-visible:ring-2 focus-visible:ring-brand-green max-vsm:min-h-[41px] ${open ? 'border-ink-secondary' : 'border-surface-border'}`}
      >
        <span className="min-w-0 flex-1 break-words">{selected?.label || placeholder}</span>
        <ChevronDown aria-hidden="true" className={`size-5 shrink-0 ${open ? 'rotate-180' : ''}`} />
      </button>
      {open && (
        <div
          id={`${id}-list`}
          role="listbox"
          aria-labelledby={`${id}-label`}
          className="absolute inset-x-0 top-full z-30 !mt-2 max-h-72 overflow-y-auto overscroll-contain rounded-card bg-surface py-2 shadow-card"
        >
          {options.map((option, index) => (
            <button
              key={option.value}
              id={`${id}-${index}`}
              type="button"
              role="option"
              aria-selected={option.value === value}
              aria-disabled={option.disabled || undefined}
              tabIndex={-1}
              onMouseDown={(event) => event.preventDefault()}
              onClick={() => choose(index)}
              className={`flex w-full items-center justify-between gap-4 px-5 py-2 text-left text-14 outline-none ${option.disabled ? 'cursor-not-allowed text-ink-secondary opacity-50' : `${option.value === value ? 'text-ink' : 'text-ink-secondary'} hover:bg-surface-grey ${active === index ? 'bg-surface-grey/60' : ''}`}`}
            >
              <span className="min-w-0 flex-1 break-words">{option.label}</span>
              <span
                aria-hidden="true"
                className={`flex size-5 shrink-0 items-center justify-center rounded-full border ${option.value === value ? 'border-brand-yellow bg-brand-yellow' : 'border-surface-border'}`}
              >
                {option.value === value && <span className="size-2 rounded-full bg-ink" />}
              </span>
            </button>
          ))}
          {!options.length && (
            <p className="px-5 py-3 text-14 text-ink-secondary">Нет доступных вариантов</p>
          )}
        </div>
      )}
    </div>
  );
}
