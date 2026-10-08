import type { FiltersMeta } from '../../../../lib/api';

export const DIMENSIONS = [
  { key: 'width', title: 'Ширина' },
  { key: 'depth', title: 'Глубина' },
  { key: 'height', title: 'Высота' },
] as const;

export type DimensionKey = (typeof DIMENSIONS)[number]['key'];
export type Bounds = { min: number; max: number };

/** Какие фильтры строки показать: только те, по которым в meta.filters есть данные (market-docs/44) */
export function filterAvailability(meta: FiltersMeta | null) {
  const hasRange = (r?: Bounds) => Boolean(r && r.max > r.min);
  const dimensions = DIMENSIONS.filter(({ key }) => hasRange(meta?.dimensions?.[key])).map((d) => ({
    ...d,
    bounds: meta!.dimensions![d.key]!,
  }));
  return {
    price: meta && hasRange(meta.price) ? meta.price : null,
    dimensions,
    manufacturers: meta?.manufacturers?.length ? meta.manufacturers : null,
    onSale: meta?.has_sale === true,
  };
}
