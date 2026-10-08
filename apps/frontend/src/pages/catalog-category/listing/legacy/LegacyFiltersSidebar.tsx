import { X } from 'lucide-react';
import type { FiltersMeta } from '../../../../lib/api';
import { resetFilters, type CatalogQuery } from '../../../../lib/catalog-query';
import FiltersSkeleton from './FiltersSkeleton';
import OptionSections from './OptionSections';
import PriceSection from './PriceSection';

interface LegacyFiltersSidebarProps {
  meta: FiltersMeta | null;
  query: CatalogQuery;
  onChange: (next: CatalogQuery) => void;
  isMobileOpen: boolean;
  onMobileClose: () => void;
}

const toggle = (values: string[], value: string) =>
  values.includes(value) ? values.filter((v) => v !== value) : [...values, value];

/** Временная панель на весь экран по «Фильтры» — до задачи 04 («Все фильтры» по макету) */
export default function LegacyFiltersSidebar({
  meta,
  query,
  onChange,
  isMobileOpen,
  onMobileClose,
}: LegacyFiltersSidebarProps) {
  const change = (patch: Partial<CatalogQuery>) => onChange({ ...query, ...patch, page: 1 });

  const toggleAttribute = (attr: string, value: string) => {
    const { [attr]: current = [], ...others } = query.attributes;
    const next = toggle(current, value);
    change({ attributes: next.length ? { ...others, [attr]: next } : others });
  };

  return (
    <div className={isMobileOpen ? 'fixed inset-0 z-50 overflow-y-auto bg-white' : 'hidden'}>
      <div className="flex items-center justify-between p-4 border-b">
        <h3 className="font-semibold text-lg">Фильтры</h3>
        <button onClick={onMobileClose} className="w-8 h-8 flex items-center justify-center">
          <X className="size-[1em] text-2xl" />
        </button>
      </div>

      <div className="mx-auto max-w-xl p-4">
        {!meta ? (
          <FiltersSkeleton />
        ) : (
          <>
            <PriceSection
              key={`${query.price.min}-${query.price.max}-${meta.price.min}-${meta.price.max}`}
              value={query.price}
              bounds={meta.price}
              onApply={(price) => {
                change({ price });
                onMobileClose();
              }}
            />
            <OptionSections
              meta={meta}
              colors={query.colors}
              sizes={query.sizes}
              attributes={query.attributes}
              onToggleColor={(slug) => change({ colors: toggle(query.colors, slug) })}
              onToggleSize={(value) => change({ sizes: toggle(query.sizes, value) })}
              onToggleAttribute={toggleAttribute}
            />
            <button
              type="button"
              onClick={() => {
                onChange(resetFilters(query));
                onMobileClose();
              }}
              className="w-full mt-4 bg-red-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors flex items-center justify-center gap-2"
            >
              Сбросить фильтры
            </button>
          </>
        )}
      </div>
    </div>
  );
}
