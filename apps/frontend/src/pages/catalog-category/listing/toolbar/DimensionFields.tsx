import { RangeInputs } from '../../../../components/ui/primitives';
import type { CatalogQuery, Range } from '../../../../lib/catalog-query';
import type { Bounds, DimensionKey } from './availability';

interface DimensionFieldsProps {
  dimensions: { key: DimensionKey; title: string; bounds: Bounds }[];
  value: Pick<CatalogQuery, DimensionKey>;
  onChange: (key: DimensionKey, range: Range) => void;
}

const toRange = ([min, max]: [number | null, number | null]): Range => ({
  ...(min !== null && { min }),
  ...(max !== null && { max }),
});

/** «Ширина / Глубина / Высота», мм: подпись 14 серым, под ней пара «от / до» (Figma 730:53359) */
export default function DimensionFields({ dimensions, value, onChange }: DimensionFieldsProps) {
  return (
    <div className="flex flex-col gap-4">
      {dimensions.map(({ key, title, bounds }) => (
        <div key={key} className="flex flex-col gap-2">
          <span aria-hidden="true" className="text-14 text-ink-secondary">
            {title}
          </span>
          <RangeInputs
            label={title}
            min={bounds.min}
            max={bounds.max}
            value={[value[key].min ?? null, value[key].max ?? null]}
            onChange={(next) => onChange(key, toRange(next))}
          />
        </div>
      ))}
    </div>
  );
}
