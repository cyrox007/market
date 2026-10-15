import type { FiltersMeta } from '../../../../lib/api';
import { manufacturerOptions, type CatalogQuery, type Range } from '../../../../lib/catalog-query';

export const DIMENSIONS = [
  { key: 'width', title: 'Ширина' },
  { key: 'depth', title: 'Глубина' },
  { key: 'height', title: 'Высота' },
] as const;

export type DimensionKey = (typeof DIMENSIONS)[number]['key'];
export type Bounds = { min: number; max: number };

const isSet = (r: Range) => r.min !== undefined || r.max !== undefined;

/** Границы из meta, расширенные выбранным: выбор не должен оказаться за краем */
function rangeBounds(
  meta: { min: number | null; max: number | null } | undefined,
  selected: Range,
): Bounds | null {
  const hasChoice = meta?.min != null && meta.max != null && meta.max > meta.min;
  if (!hasChoice && !isSet(selected)) return null;
  // Габарита без данных бэкенд отдаёт null (market-docs/44, ответ 3 от 14.10)
  const values = [meta?.min, meta?.max, selected.min, selected.max].filter(
    (v): v is number => v != null,
  );
  return { min: Math.min(...values), max: Math.max(...values) };
}

/**
 * Какие фильтры строки показать (market-docs/44, 47): есть из чего выбрать в meta.filters —
 * или уже выбрано (иначе выбор не снять).
 */
export function filterAvailability(meta: FiltersMeta | null, query: CatalogQuery) {
  const dimensions = DIMENSIONS.flatMap((d) => {
    const bounds = rangeBounds(meta?.dimensions?.[d.key], query[d.key]);
    return bounds ? [{ ...d, bounds }] : [];
  });
  const manufacturers = manufacturerOptions(meta);
  return {
    price: meta ? rangeBounds(meta.price, query.price) : null,
    dimensions,
    manufacturers: manufacturers.length || query.manufacturers.length ? manufacturers : null,
    onSale: meta?.has_sale === true || query.onSale,
  };
}
