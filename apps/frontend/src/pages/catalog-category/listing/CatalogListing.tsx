import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import type { Category } from '../../../lib/api';
import type { CatalogScope } from '../../../lib/catalog-query';
import { PAGE_CONTAINER } from '../../../lib/layout';
import CatalogHeader from './CatalogHeader';
import { countProductsIn } from './countProducts';
import LegacyFiltersSidebar from './legacy/LegacyFiltersSidebar';
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
  const countProducts = useMemo(() => countProductsIn(scope), [scope]);
  // До задачи 04 «Фильтры» открывают старую панель
  const [allFiltersOpen, setAllFiltersOpen] = useState(false);

  return (
    <main className="bg-surface">
      <div
        className={`${PAGE_CONTAINER} pb-16 pt-6 max-md:pb-12 max-md:pt-5 max-vsm:pb-10 max-vsm:pt-4`}
      >
        <CatalogHeader
          category={category}
          title={displayName}
          total={listing.isLoading ? null : listing.total}
        />

        {category?.children && category.children.length > 0 && (
          <div className="mt-6 flex flex-wrap gap-2 md:gap-3">
            {category.children.map((subcat) => (
              <Link
                key={subcat.id}
                to={`/catalog/${subcat.slug}`}
                className="whitespace-nowrap rounded-pill bg-surface-grey px-4 py-2 text-14 text-ink transition-colors hover:bg-brand-green hover:text-ink-inverse"
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
            query={query}
            onQueryChange={setQuery}
            meta={listing.filtersMeta}
            total={listing.total}
            countProducts={countProducts}
            onOpenAllFilters={() => setAllFiltersOpen(true)}
          />
        </div>

        <LegacyFiltersSidebar
          meta={listing.filtersMeta}
          query={query}
          onChange={setQuery}
          isMobileOpen={allFiltersOpen}
          onMobileClose={() => setAllFiltersOpen(false)}
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
