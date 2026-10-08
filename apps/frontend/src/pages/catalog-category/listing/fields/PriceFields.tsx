import { RangeInputs, RangeSlider } from '../../../../components/ui/primitives';
import type { Range } from '../../../../lib/catalog-query';

interface PriceFieldsProps {
  value: Range;
  bounds: { min: number; max: number };
  onChange: (range: Range) => void;
}

const PRICE_STEP = 100;

/** Граница, равная пределу категории, — не фильтр: в адрес не пишем */
function toRange(
  min: number | null,
  max: number | null,
  bounds: PriceFieldsProps['bounds'],
): Range {
  return {
    ...(min !== null && min > bounds.min && { min }),
    ...(max !== null && max < bounds.max && { max }),
  };
}

export default function PriceFields({ value, bounds, onChange }: PriceFieldsProps) {
  return (
    <div className="flex flex-col gap-4">
      <RangeInputs
        label="Цена"
        className="max-w-full md:w-[280px]"
        min={bounds.min}
        max={bounds.max}
        value={[value.min ?? null, value.max ?? null]}
        onChange={([min, max]) => onChange(toRange(min, max, bounds))}
      />
      <RangeSlider
        label="Цена"
        min={bounds.min}
        max={bounds.max}
        step={PRICE_STEP}
        value={[value.min ?? bounds.min, value.max ?? bounds.max]}
        onChange={([min, max]) => onChange(toRange(min, max, bounds))}
      />
    </div>
  );
}
