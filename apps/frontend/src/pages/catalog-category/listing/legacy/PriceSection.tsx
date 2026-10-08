import { useState } from 'react';
import type { Range } from '../../../../lib/catalog-query';

interface PriceSectionProps {
  value: Range;
  bounds: { min: number; max: number };
  onApply: (range: Range) => void;
}

export default function PriceSection({ value, bounds, onApply }: PriceSectionProps) {
  const [min, setMin] = useState(value.min ?? bounds.min);
  const [max, setMax] = useState(value.max ?? bounds.max);

  const apply = () =>
    onApply({
      ...(min > bounds.min && { min }),
      ...(max < bounds.max && { max }),
    });

  return (
    <div className="mb-5">
      <label className="block text-sm font-medium mb-2">Цена</label>
      <div className="flex gap-2 mb-2">
        <input
          type="number"
          value={min}
          onChange={(e) => setMin(+e.target.value)}
          className="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm"
          placeholder="От"
        />
        <input
          type="number"
          value={max}
          onChange={(e) => setMax(+e.target.value)}
          className="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm"
          placeholder="До"
        />
      </div>
      <button
        onClick={apply}
        className="w-full bg-red-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"
      >
        Применить
      </button>
    </div>
  );
}
