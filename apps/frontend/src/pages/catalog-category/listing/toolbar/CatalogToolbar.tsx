import type { FiltersMeta } from '../../../../lib/api';
import { activeFilterCount, type CatalogQuery } from '../../../../lib/catalog-query';
import { useMediaQuery } from '../../../../hooks/useMediaQuery';
import { useFilterDraft } from '../useFilterDraft';
import AllFiltersButton from './AllFiltersButton';
import { filterAvailability } from './availability';
import DimensionFields from './DimensionFields';
import FilterDropdown from './FilterDropdown';
import ManufacturerList from './ManufacturerList';
import OnSalePill from './OnSalePill';
import PriceFields from './PriceFields';
import SortControl from './SortControl';

export interface CatalogToolbarProps {
  /** Применённые фильтры — из адреса */
  query: CatalogQuery;
  onQueryChange: (next: CatalogQuery) => void;
  meta: FiltersMeta | null;
  /** Товаров по применённым фильтрам */
  total: number;
  countProducts: (query: CatalogQuery) => Promise<number>;
  onOpenAllFilters: () => void;
}

/**
 * Строка фильтров над списком (market-docs/44, «Фильтры»): от 980 — кнопки с окнами,
 * ниже — сортировка слева, «Фильтры (N)» справа.
 */
export default function CatalogToolbar({
  query,
  onQueryChange,
  meta,
  total,
  countProducts,
  onOpenAllFilters,
}: CatalogToolbarProps) {
  const compact = useMediaQuery('(max-width: 979.98px)');
  const draft = useFilterDraft({
    applied: query,
    appliedTotal: total,
    onApply: onQueryChange,
    countProducts,
  });
  const available = filterAvailability(meta);
  const applyNow = (patch: Partial<CatalogQuery>) => onQueryChange({ ...query, ...patch, page: 1 });

  const sort = (
    <SortControl value={query.sort} asSheet={compact} onChange={(s) => applyNow({ sort: s })} />
  );
  const allFilters = (
    <AllFiltersButton count={activeFilterCount(query)} onClick={onOpenAllFilters} />
  );

  if (compact) {
    return (
      <div className="flex items-center justify-between gap-2">
        {sort}
        {allFilters}
      </div>
    );
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      {allFilters}
      {sort}
      {available.price && (
        <FilterDropdown label="Цена" surface="price" draft={draft} className="w-[322px]">
          <PriceFields
            value={draft.draft.price}
            bounds={available.price}
            onChange={(price) => draft.set({ price })}
          />
        </FilterDropdown>
      )}
      {available.dimensions.length > 0 && (
        <FilterDropdown label="Габариты" surface="dimensions" draft={draft} className="w-[320px]">
          <DimensionFields
            dimensions={available.dimensions}
            value={draft.draft}
            onChange={(key, range) => draft.set({ [key]: range })}
          />
        </FilterDropdown>
      )}
      {available.manufacturers && (
        <FilterDropdown
          label="Производители"
          surface="manufacturers"
          draft={draft}
          className="w-[264px]"
        >
          <ManufacturerList
            options={available.manufacturers}
            value={draft.draft.manufacturers}
            onChange={(manufacturers) => draft.set({ manufacturers })}
          />
        </FilterDropdown>
      )}
      {available.onSale && (
        <OnSalePill checked={query.onSale} onChange={(onSale) => applyNow({ onSale })} />
      )}
    </div>
  );
}
