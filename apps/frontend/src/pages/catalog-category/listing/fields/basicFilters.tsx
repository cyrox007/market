import type { ReactNode } from 'react';
import type { CatalogQuery } from '../../../../lib/catalog-query';
import type { filterAvailability } from './availability';
import DimensionFields from './DimensionFields';
import ManufacturerList from './ManufacturerList';
import PriceFields from './PriceFields';

export type BasicFilterKey = 'price' | 'dimensions' | 'manufacturers';

/** Цена, габариты, производители — в строке над списком и в «Все фильтры», в этом порядке */
export function basicFilters(
  available: ReturnType<typeof filterAvailability>,
  value: CatalogQuery,
  set: (patch: Partial<CatalogQuery>) => void,
): { key: BasicFilterKey; body: ReactNode }[] {
  const items: { key: BasicFilterKey; body: ReactNode }[] = [];
  if (available.price) {
    items.push({
      key: 'price',
      body: (
        <PriceFields
          value={value.price}
          bounds={available.price}
          onChange={(price) => set({ price })}
        />
      ),
    });
  }
  if (available.dimensions.length > 0) {
    items.push({
      key: 'dimensions',
      body: (
        <DimensionFields
          dimensions={available.dimensions}
          value={value}
          onChange={(key, range) => set({ [key]: range })}
        />
      ),
    });
  }
  if (available.manufacturers) {
    items.push({
      key: 'manufacturers',
      body: (
        <ManufacturerList
          options={available.manufacturers}
          value={value.manufacturers}
          onChange={(manufacturers) => set({ manufacturers })}
        />
      ),
    });
  }
  return items;
}
