import type { ProductColorRow } from '../../../lib/api';
import ColorSample from './ColorSample';

export default function SelectedColors({ colors }: { colors?: ProductColorRow[] }) {
  if (!colors?.length) return null;
  return (
    <div className="space-y-1.5 text-14 text-ink-secondary">
      {colors.map((row) => (
        <div
          key={`${row.element?.slug ?? 'main'}:${row.solution.id}`}
          className="flex items-center gap-2"
        >
          <ColorSample solution={row.solution} className="!size-[18px]" />
          <span className="min-w-0 break-words">
            {[row.element?.name, row.solution.name].filter(Boolean).join(': ')}
          </span>
        </div>
      ))}
    </div>
  );
}
