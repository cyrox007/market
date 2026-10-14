import { Button } from '../../../../components/ui/primitives';
import { formatNumber } from '../../../../components/ui/primitives/digits';
import { activeFilterCount, type CatalogQuery, type Range } from '../../../../lib/catalog-query';
import { plural } from '../../../../lib/plural';

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
  const hint = [
    range
      ? `Попробуйте расширить диапазон ${range} или снять часть фильтров.`
      : 'Попробуйте снять часть фильтров.',
    sectionTotal
      ? `Всего в разделе ${formatNumber(sectionTotal)} ${plural(sectionTotal, PRODUCTS)}`
      : null,
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <section className="flex flex-col items-center rounded-card bg-surface-grey px-6 py-12 text-center max-vsm:px-4 max-vsm:py-8">
      <h2 className="text-18 font-semibold text-ink max-vsm:text-16">
        {filtered ? 'По вашим фильтрам ничего не нашлось' : 'В этом разделе пока нет товаров'}
      </h2>
      {filtered && (
        <>
          <p className="mt-2 max-w-[480px] text-14 leading-[1.45] text-ink-secondary">{hint}</p>
          <Button size="lg" shape="rounded" onClick={onReset} className="mt-6 max-vsm:w-full">
            Сбросить фильтры
          </Button>
        </>
      )}
    </section>
  );
}
