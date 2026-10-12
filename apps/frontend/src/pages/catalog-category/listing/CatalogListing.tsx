import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import type { Category } from '../../../lib/api';
import type { CatalogScope } from '../../../lib/catalog-query';
import { PAGE_CONTAINER } from '../../../lib/layout';
import { useMediaQuery } from '../../../hooks/useMediaQuery';
import CatalogHeader, { ProductCount } from './CatalogHeader';
import FilterChips from './chips/FilterChips';
import { countProductsIn } from './countProducts';
import AllFiltersPanel from './all-filters/AllFiltersPanel';
import ProductGrid from './ProductGrid';
import CatalogToolbar from './toolbar/CatalogToolbar';
import { useCatalogListing, type CatalogListingOptions } from './useCatalogListing';
import { useUrlCatalogQuery } from './useUrlCatalogQuery';

interface CatalogListingProps {
  category: Category | null;
  displayName: string;
  scope: CatalogScope;
  initial: CatalogListingOptions['initial'];
  onPrefetchCategory: (slug: string) => void;
}

/** Список товаров конечной категории и комнаты (market-docs/43, задачи 03–06) */
export default function CatalogListing({
  category,
  displayName,
  scope,
  initial,
  onPrefetchCategory,
}: CatalogListingProps) {
  const [query, setQuery] = useUrlCatalogQuery();
  const listing = useCatalogListing({ scope, query, onQueryChange: setQuery, initial });
  const shownTotal = listing.isLoading ? null : listing.total;
  const countProducts = useMemo(() => countProductsIn(scope), [scope]);
  // Один раз на странице: панель создаётся при каждом открытии, а хук в первом кадре даёт false
  const compact = useMediaQuery('(max-width: 979.98px)');
  const [allFiltersOpen, setAllFiltersOpen] = useState(false);
  // Новое открытие — новый экземпляр панели со свежим черновиком
  const [allFiltersSession, setAllFiltersSession] = useState(0);
  const openAllFilters = () => {
    setAllFiltersSession((n) => n + 1);
    setAllFiltersOpen(true);
  };
  const subcategories = useMemo(
    () => (category?.children ?? []).map((c) => ({ slug: c.slug, name: c.name })),
    [category],
  );

  return (
    <main className="bg-surface">
      <div
        className={`${PAGE_CONTAINER} pb-16 pt-6 max-md:pb-12 max-md:pt-5 max-vsm:pb-10 max-vsm:pt-4`}
      >
        <CatalogHeader category={category} title={displayName} total={shownTotal} />

        {category?.children && category.children.length > 0 && (
          <div className="mt-6 flex flex-wrap gap-2 md:gap-3">
            {category.children.map((subcat) => (
              <Link
                key={subcat.id}
                to={`/catalog/${subcat.slug}`}
                className="whitespace-nowrap rounded-pill bg-surface-grey px-4 py-2 text-14 text-ink outline-none transition-colors hover:bg-surface-border focus-visible:ring-2 focus-visible:ring-brand-green"
                onMouseEnter={() => onPrefetchCategory(subcat.slug)}
                onFocus={() => onPrefetchCategory(subcat.slug)}
              >
                {subcat.name}
              </Link>
            ))}
          </div>
        )}

        <div className="mt-6 max-vsm:mt-5">
          <CatalogToolbar
            compact={compact}
            query={query}
            onQueryChange={setQuery}
            meta={listing.filtersMeta}
            total={listing.total}
            countProducts={countProducts}
            onOpenAllFilters={openAllFilters}
          />
        </div>

        <div className="mt-4 empty:hidden">
          <FilterChips
            query={query}
            meta={listing.filtersMeta}
            subcategories={subcategories}
            onQueryChange={setQuery}
          />
        </div>

        <p className="mt-4 empty:hidden md:hidden">
          <ProductCount total={shownTotal} />
        </p>

        <AllFiltersPanel
          key={allFiltersSession}
          open={allFiltersOpen}
          compact={compact}
          query={query}
          onQueryChange={setQuery}
          onClose={() => setAllFiltersOpen(false)}
          meta={listing.filtersMeta}
          total={listing.total}
          countProducts={countProducts}
          subcategories={subcategories}
        />

        <div className="mt-6">
          <ProductGrid
            products={listing.products}
            isLoading={listing.isLoading}
            isLoadingMore={listing.isLoadingMore}
            hasMore={listing.hasMore}
            onLoadMore={listing.loadMore}
          />
        </div>
      </div>
    </main>
  );
}
