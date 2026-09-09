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
  className,
}: QuantityStepperProps) {
  const canDecrement = value > min;
  const canIncrement = max === undefined || value < max;

  return (
    <div className={cn('inline-flex items-center gap-2', className)}>
      <IconButton
        label={decrementLabel}
        variant="grey"
        elevated
        disabled={!canDecrement}
        onClick={() => onChange?.(value - 1)}
      >
        <Minus className="size-5" />
      </IconButton>

      <span
        aria-live="polite"
        className="inline-flex h-10 w-[55px] shrink-0 items-center justify-center rounded-btn bg-surface text-14 text-ink"
      >
        {value}
      </span>

      <IconButton
        label={incrementLabel}
        elevated
        disabled={!canIncrement}
        onClick={() => onChange?.(value + 1)}
      >
        <Plus className="size-5" />
      </IconButton>
    </div>
  );
}
