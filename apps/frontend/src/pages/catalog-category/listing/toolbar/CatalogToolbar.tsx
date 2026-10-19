import type { FiltersMeta } from '../../../../lib/api';
import {
  activeFilterCount,
  CATALOG_SORT_OPTIONS,
  type CatalogQuery,
} from '../../../../lib/catalog-query';
import { useFilterDraft } from '../useFilterDraft';
import AllFiltersButton from './AllFiltersButton';
import { filterAvailability } from '../fields/availability';
import { basicFilters, type BasicFilterKey } from '../fields/basicFilters';
import FilterDropdown from './FilterDropdown';
import OnSalePill from './OnSalePill';
import SortControl from '../../../../components/catalog/SortControl';

export interface CatalogToolbarProps {
  /** Экран уже 980 — определяет страница один раз, чтобы первый кадр не был «десктопным» */
  compact: boolean;
  /** Применённые фильтры — из адреса */
  query: CatalogQuery;
  onQueryChange: (next: CatalogQuery) => void;
  meta: FiltersMeta | null;
  /** Товаров по применённым фильтрам */
  total: number | null;
  countProducts: (query: CatalogQuery) => Promise<number>;
  onOpenAllFilters: () => void;
}

const DROPDOWNS: Record<BasicFilterKey, { label: string; className: string }> = {
  price: { label: 'Цена', className: 'w-[322px]' },
  dimensions: { label: 'Габариты', className: 'w-[320px]' },
  manufacturers: { label: 'Производители', className: 'w-[264px]' },
};

/**
 * Строка фильтров над списком (market-docs/44, «Фильтры»): от 980 — кнопки с окнами,
 * ниже — сортировка слева, «Фильтры (N)» справа.
 */
export default function CatalogToolbar({
  compact,
  query,
  onQueryChange,
  meta,
  total,
  countProducts,
  onOpenAllFilters,
}: CatalogToolbarProps) {
  const draft = useFilterDraft({
    applied: query,
    appliedTotal: total,
    onApply: onQueryChange,
    countProducts,
  });
  const available = filterAvailability(meta, query);
  const applyNow = (patch: Partial<CatalogQuery>) => onQueryChange({ ...query, ...patch, page: 1 });

  const sort = (
    <SortControl
      options={CATALOG_SORT_OPTIONS}
      value={query.sort}
      asSheet={compact}
      onChange={(s) => applyNow({ sort: s })}
    />
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
      {basicFilters(available, draft.draft, draft.set).map(({ key, body }) => (
        <FilterDropdown
          key={key}
          label={DROPDOWNS[key].label}
          surface={key}
          draft={draft}
          className={DROPDOWNS[key].className}
        >
          {body}
        </FilterDropdown>
      ))}
      {available.onSale && (
        <OnSalePill checked={query.onSale} onChange={(onSale) => applyNow({ onSale })} />
      )}
    </div>
  );
}
