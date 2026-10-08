import { useRef } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';
import { formatNumber } from './digits';
import { cn } from '../../../lib/cn';

interface RangeSliderProps {
  /** Название диапазона: подписи бегунков «Цена от» / «Цена до» */
  label: string;
  min: number;
  max: number;
  step?: number;
  value: [number, number];
  /** На каждое движение — чтобы поля «от / до» шли следом */
  onChange: (value: [number, number]) => void;
  /** Когда движение закончено: отпустили бегунок или нажали клавишу — тут запрашивать товары */
  onChangeEnd?: (value: [number, number]) => void;
  formatValue?: (n: number) => string;
  disabled?: boolean;
  className?: string;
}

type Thumb = 0 | 1;

/** Диаметр бегунка, px. Бегунок не вылезает за края полосы — ход короче ширины на него */
const THUMB = 16;

/**
 * Ползунок диапазона с двумя бегунками: мышь, палец, клавиатура.
 * Замеры — market-docs/45.
 */
export default function RangeSlider({
  label,
  min,
  max,
  step = 1,
  value,
  onChange,
  onChangeEnd,
  formatValue = formatNumber,
  disabled = false,
  className,
}: RangeSliderProps) {
  const rootRef = useRef<HTMLDivElement>(null);
  const thumbs = useRef<[HTMLDivElement | null, HTMLDivElement | null]>([null, null]);
  const drag = useRef<{ thumb: Thumb; last: [number, number] } | null>(null);

  const [low, high] = value;
  const span = max - min || 1;
  const share = (n: number) => (n - min) / span;

  const snap = (n: number) => {
    const stepped = min + Math.round((n - min) / step) * step;
    return Math.min(max, Math.max(min, stepped));
  };

  /** Новое значение одного бегунка, не заходящее за другой */
  const withThumb = (thumb: Thumb, n: number): [number, number] =>
    thumb === 0 ? [Math.min(snap(n), high), high] : [low, Math.max(snap(n), low)];

  const emit = (next: [number, number]) => {
    if (next[0] === low && next[1] === high) return false;
    onChange(next);
    return true;
  };

  const handleKeyDown = (thumb: Thumb) => (event: KeyboardEvent<HTMLDivElement>) => {
    const current = value[thumb];
    const moves: Record<string, number> = {
      ArrowRight: current + step,
      ArrowUp: current + step,
      ArrowLeft: current - step,
      ArrowDown: current - step,
      PageUp: current + step * 10,
      PageDown: current - step * 10,
      Home: min,
      End: max,
    };
    if (!(event.key in moves)) return;
    event.preventDefault();
    const next = withThumb(thumb, moves[event.key]);
    if (emit(next)) onChangeEnd?.(next);
  };

  const valueAt = (clientX: number) => {
    const rect = rootRef.current!.getBoundingClientRect();
    const travel = rect.width - THUMB || 1;
    const ratio = Math.min(1, Math.max(0, (clientX - rect.left - THUMB / 2) / travel));
    return min + ratio * span;
  };

  const handlePointerDown = (event: PointerEvent<HTMLDivElement>) => {
    if (disabled || event.button > 0) return;
    const n = valueAt(event.clientX);
    // При совпавших бегунках тянем тот, в чью сторону нажали
    const thumb: Thumb =
      Math.abs(n - low) < Math.abs(n - high) || (low === high && n < low) ? 0 : 1;
    event.currentTarget.setPointerCapture?.(event.pointerId);
    thumbs.current[thumb]?.focus({ preventScroll: true });
    const next = withThumb(thumb, n);
    drag.current = { thumb, last: next };
    emit(next);
  };

  const handlePointerMove = (event: PointerEvent<HTMLDivElement>) => {
    if (!drag.current) return;
    const next = withThumb(drag.current.thumb, valueAt(event.clientX));
    if (next[0] === drag.current.last[0] && next[1] === drag.current.last[1]) return;
    drag.current.last = next;
    onChange(next);
  };

  const handlePointerUp = () => {
    if (!drag.current) return;
    onChangeEnd?.(drag.current.last);
    drag.current = null;
  };

  const thumbProps = (thumb: Thumb) => ({
    ref: (el: HTMLDivElement | null) => {
      thumbs.current[thumb] = el;
    },
    role: 'slider',
    tabIndex: disabled ? -1 : 0,
    'aria-label': `${label} ${thumb === 0 ? 'от' : 'до'}`,
    'aria-valuemin': thumb === 0 ? min : low,
    'aria-valuemax': thumb === 0 ? high : max,
    'aria-valuenow': value[thumb],
    'aria-valuetext': formatValue(value[thumb]),
    'aria-disabled': disabled || undefined,
    onKeyDown: disabled ? undefined : handleKeyDown(thumb),
    style: { left: `calc((100% - ${THUMB}px) * ${share(value[thumb])})` },
    className: cn(
      'absolute top-0 size-4 rounded-full touch-none outline-none',
      // Палец крупнее бегунка: зона касания шире на 12 px с каждой стороны
      "before:absolute before:-inset-3 before:content-['']",
      'focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2',
      disabled ? 'bg-surface-border' : 'cursor-grab bg-brand-yellow active:cursor-grabbing',
    ),
  });

  return (
    <div
      ref={rootRef}
      onPointerDown={handlePointerDown}
      onPointerMove={handlePointerMove}
      onPointerUp={handlePointerUp}
      onPointerCancel={handlePointerUp}
      className={cn('relative h-4 w-full touch-pan-y select-none', className)}
    >
      <div className="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 rounded-lg bg-surface-border/50" />
      <div
        className={cn(
          'absolute top-1/2 h-1 -translate-y-1/2 rounded-lg',
          disabled ? 'bg-surface-border' : 'bg-brand-yellow',
        )}
        style={{
          left: `calc((100% - ${THUMB}px) * ${share(low)} + ${THUMB / 2}px)`,
          width: `calc((100% - ${THUMB}px) * ${share(high) - share(low)})`,
        }}
      />
      <div {...thumbProps(0)} />
      <div {...thumbProps(1)} />
    </div>
  );
}
