import { Button } from '../../../../components/ui/primitives';
import { formatNumber } from '../../../../components/ui/primitives/digits';
import { activeFilterCount, type CatalogQuery, type Range } from '../../../../lib/catalog-query';
import { plural } from '../../../../lib/plural';
import EmptyState from './EmptyState';

interface EmptyResultsProps {
  query: CatalogQuery;
  /** Товаров в разделе без фильтров (category.products_count); null — неизвестно */
  sectionTotal: number | null;
  onReset: () => void;
}

const PRODUCTS = ['товар', 'товара', 'товаров'] as const;
const isSet = (r: Range) => r.min !== undefined || r.max !== undefined;

/** Какой диапазон расширять: «габаритов», «цены» или оба (market-docs/44) */
function rangeHint(q: CatalogQuery): string | null {
  const parts = [
    (isSet(q.width) || isSet(q.depth) || isSet(q.height)) && 'габаритов',
    isSet(q.price) && 'цены',
  ].filter(Boolean);
  return parts.length ? parts.join(' / ') : null;
}

export default function EmptyResults({ query, sectionTotal, onReset }: EmptyResultsProps) {
  const filtered = activeFilterCount(query) > 0;
  const range = rangeHint(query);
  const tail = sectionTotal
    ? `, всего в разделе ${formatNumber(sectionTotal)} ${plural(sectionTotal, PRODUCTS)}.`
    : '.';
  const hint = range
    ? `Попробуйте расширить диапазон ${range} или снять часть фильтров${tail}`
    : `Попробуйте снять часть фильтров${tail}`;

  return (
    <EmptyState
      title={filtered ? 'По вашим фильтрам ничего не нашлось' : 'В этом разделе пока нет товаров'}
      hint={filtered ? hint : undefined}
      action={
        filtered && (
          <Button
            size="lg"
            shape="rounded"
            onClick={onReset}
            className="w-[200px] px-7 shadow-card max-vsm:w-full"
          >
            Сбросить фильтры
          </Button>
        )
      }
    />
  );
}
