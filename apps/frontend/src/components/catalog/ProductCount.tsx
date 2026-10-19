import { plural } from '../../lib/plural';
import { formatNumber } from '../ui/primitives/digits';

const PRODUCTS = ['товар', 'товара', 'товаров'] as const;

/** «226 товаров»; null — ещё не загружено */
export default function ProductCount({
  total,
  className,
}: {
  total: number | null;
  className?: string;
}) {
  if (total === null) return null;
  return (
    <span className={`text-16 text-ink-secondary max-vsm:text-14 ${className ?? ''}`}>
      {formatNumber(total)} {plural(total, PRODUCTS)}
    </span>
  );
}
