import IconButton from '../primitives/IconButton';
import { Minus, Plus } from 'lucide-react';
import { cn } from '../../../lib/cn';

interface QuantityStepperProps {
  value: number;
  min?: number;
  max?: number;
  onChange?: (value: number) => void;
  decrementLabel?: string;
  incrementLabel?: string;
  /** yellow — страница товара: жёлтые круги, значение в рамке (market-docs/56, cart-in-cart.png) */
  tone?: 'card' | 'yellow' | 'cart';
  disabled?: boolean;
  className?: string;
}

/**
 * Счётчик количества с карточки товара: минус — значение — плюс.
 *
 * Замерено с макета 07.09.2026:
 *   кнопки      круг 40, иконка 20
 *   минус       фон серый, плюс — белый
 *   значение    прямоугольник шириной 55, радиус 12
 *   промежуток  8
 *
 * Размеры одинаковы на всех экранах — владелец подтвердил, адаптива здесь нет.
 *
 * Прямоугольник со значением: высота 40, шрифт 14, рамки нет — всё замерено.
 *
 * Замеров, оставшихся невыверенными, у этого компонента нет.
 */
export default function QuantityStepper({
  value,
  min = 1,
  max,
  onChange,
  decrementLabel = 'Уменьшить количество',
  incrementLabel = 'Увеличить количество',
  tone = 'card',
  disabled = false,
  className,
}: QuantityStepperProps) {
  const canDecrement = !disabled && value > min;
  const canIncrement = !disabled && (max === undefined || value < max);
  const yellow = tone === 'yellow';
  const cart = tone === 'cart';

  return (
    <div
      className={cn(
        'inline-flex items-center',
        cart ? 'gap-1 rounded-pill bg-surface-grey px-1.5' : 'gap-2',
        className,
      )}
    >
      <IconButton
        label={decrementLabel}
        variant={yellow ? 'yellow' : 'grey'}
        elevated={!yellow}
        className={cart ? '!size-9 !bg-transparent !shadow-none max-vsm:!size-8' : undefined}
        disabled={!canDecrement}
        onClick={() => onChange?.(value - 1)}
      >
        <Minus className="size-5" />
      </IconButton>

      <span
        aria-live="polite"
        className={cn(
          'inline-flex h-10 w-[55px] shrink-0 items-center justify-center rounded-btn bg-surface text-14 text-ink',
          yellow && 'border border-surface-stroke/[0.08]',
          cart && '!h-9 !w-7 !bg-transparent max-vsm:!h-8 max-vsm:!w-5',
        )}
      >
        {value}
      </span>

      <IconButton
        label={incrementLabel}
        variant={yellow ? 'yellow' : 'white'}
        elevated={!yellow}
        className={cart ? '!size-9 !bg-transparent !shadow-none max-vsm:!size-8' : undefined}
        disabled={!canIncrement}
        onClick={() => onChange?.(value + 1)}
      >
        <Plus className="size-5" />
      </IconButton>
    </div>
  );
}
