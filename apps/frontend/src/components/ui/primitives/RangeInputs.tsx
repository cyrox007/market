import { useLayoutEffect, useRef, useState } from 'react';
import type { ChangeEvent, KeyboardEvent } from 'react';
import Input from './Input';
import { formatNumber, groupDigits } from './digits';
import { cn } from '../../../lib/cn';

/** `null` — граница не задана, фильтр с этой стороны не ограничен */
export type RangeValue = [number | null, number | null];

interface RangeInputsProps {
  /** Название диапазона: из него собираются подписи «Цена от» / «Цена до» */
  label: string;
  min: number;
  max: number;
  value: RangeValue;
  /** Вызывается при выходе из поля или Enter, а не на каждую цифру */
  onChange: (value: RangeValue) => void;
  disabled?: boolean;
  className?: string;
}

const MAX_DIGITS = 9;

/** Позиция после n-й цифры в отформатированной строке */
function caretAfterDigits(text: string, n: number): number {
  if (n === 0) return 0;
  let seen = 0;
  for (let i = 0; i < text.length; i += 1) {
    if (/\d/.test(text[i]) && ++seen === n) return i + 1;
  }
  return text.length;
}

function clamp(n: number, min: number, max: number) {
  return Math.min(max, Math.max(min, n));
}

/**
 * Пара полей «от … / до …» с разрядами через пробел.
 * Пока поле в фокусе, в нём черновик; наружу значение уходит при выходе или Enter:
 * подтянутое к границам и упорядоченное (market-docs/45).
 */
export default function RangeInputs({
  label,
  min,
  max,
  value,
  onChange,
  disabled,
  className,
}: RangeInputsProps) {
  const [draft, setDraft] = useState<{ index: 0 | 1; text: string } | null>(null);
  const inputs = useRef<[HTMLInputElement | null, HTMLInputElement | null]>([null, null]);
  const pendingCaret = useRef<number | null>(null);

  useLayoutEffect(() => {
    if (!draft || pendingCaret.current === null) return;
    inputs.current[draft.index]?.setSelectionRange(pendingCaret.current, pendingCaret.current);
    pendingCaret.current = null;
  }, [draft]);

  const shown = (index: 0 | 1) => {
    if (draft?.index === index) return draft.text;
    const n = value[index];
    return n === null ? '' : formatNumber(n);
  };

  const handleChange = (index: 0 | 1) => (event: ChangeEvent<HTMLInputElement>) => {
    const raw = event.target.value;
    const caret = event.target.selectionStart ?? raw.length;
    const digitsBefore = raw.slice(0, caret).replace(/\D/g, '').length;
    const digits = raw.replace(/\D/g, '').slice(0, MAX_DIGITS);
    const text = groupDigits(digits);
    pendingCaret.current = caretAfterDigits(text, Math.min(digitsBefore, digits.length));
    setDraft({ index, text });
  };

  const commit = () => {
    if (!draft) return;
    const digits = draft.text.replace(/\D/g, '');
    const typed = digits === '' ? null : clamp(Number(digits), min, max);
    setDraft(null);

    const next: RangeValue = draft.index === 0 ? [typed, value[1]] : [value[0], typed];
    if (next[0] !== null && next[1] !== null && next[0] > next[1]) next.reverse();
    if (next[0] !== value[0] || next[1] !== value[1]) onChange(next);
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter') commit();
  };

  const field = (index: 0 | 1) => {
    const prefix = index === 0 ? 'от' : 'до';
    const bound = index === 0 ? min : max;
    return (
      <Input
        ref={(el) => {
          inputs.current[index] = el;
        }}
        tone="outline"
        shape="field"
        wrapperClassName="min-w-0 flex-1"
        inputMode="numeric"
        autoComplete="off"
        aria-label={`${label} ${prefix}`}
        placeholder={`${prefix} ${formatNumber(bound)}`}
        value={shown(index)}
        disabled={disabled}
        onFocus={() => setDraft({ index, text: shown(index) })}
        onChange={handleChange(index)}
        onBlur={commit}
        onKeyDown={handleKeyDown}
      />
    );
  };

  return (
    <div className={cn('flex gap-2.5', className)}>
      {field(0)}
      {field(1)}
    </div>
  );
}
