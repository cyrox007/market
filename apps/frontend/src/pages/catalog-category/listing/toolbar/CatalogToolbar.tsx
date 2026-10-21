import type { FiltersMeta } from '../../../../lib/api';
import { cn } from '../../../../lib/cn';
import {
  activeFilterCount,
  catalogSortOptions,
  DEFAULT_SORT,
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
  /** Экран уже 980; null — ещё неизвестно (сервер, первый кадр): обе раскладки, выбирает CSS */
  compact: boolean | null;
  /** Применённые фильтры — из адреса */
  query: CatalogQuery;
  onQueryChange: (next: CatalogQuery) => void;
  meta: FiltersMeta | null;
  /** Товаров по применённым фильтрам */
  total: number | null;
  countProducts: (query: CatalogQuery) => Promise<number>;
  onOpenAllFilters: () => void;
  /** Выбран пункт — есть «Сначала ближе» */
  nearby: boolean;
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
  nearby,
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
      options={catalogSortOptions(nearby)}
      // Пункт сбросили — по расстоянию уже не сортируется (toApiParams), показываем как есть
      value={query.sort === 'nearest' && !nearby ? DEFAULT_SORT : query.sort}
      asSheet={compact === true}
      onChange={(s) => applyNow({ sort: s })}
    />
  );
  const allFilters = (
    <AllFiltersButton count={activeFilterCount(query)} onClick={onOpenAllFilters} />
  );

  const phone = (
    <div className={cn('flex items-center justify-between gap-2', compact === null && 'md:hidden')}>
      {sort}
      {allFilters}
    </div>
  );
  if (compact) return phone;

  const desktop = (
    <div className={cn('flex flex-wrap items-center gap-2', compact === null && 'max-md:hidden')}>
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
  if (compact === false) return desktop;

  return (
    <>
      {phone}
      {desktop}
    </>
  );
}
