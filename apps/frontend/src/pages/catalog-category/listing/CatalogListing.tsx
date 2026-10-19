import { useCallback, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import type { Category } from '../../../lib/api';
import { resetFilters, type CatalogScope } from '../../../lib/catalog-query';
import { BELOW_MD, PAGE_CONTAINER } from '../../../lib/layout';
import { scrollToTop } from '../../../lib/motion';
import { useMediaMatch } from '../../../hooks/useMediaQuery';
import CatalogHeader from './CatalogHeader';
import ProductCount from '../../../components/catalog/ProductCount';
import CategoryDescription from '../components/CategoryDescription';
import FilterChips from './chips/FilterChips';
import { countProductsIn } from './countProducts';
import AllFiltersPanel from './all-filters/AllFiltersPanel';
import EmptyResults from './empty/EmptyResults';
import CatalogPagination from './pagination/CatalogPagination';
import ProductGrid from '../../../components/catalog/ProductGrid';
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
  // Пока новый список не пришёл, на экране прежний — его число для «Показать N» уже неверно
  const settledTotal = listing.isLoading || listing.isRefreshing ? null : listing.total;
  const countProducts = useMemo(() => countProductsIn(scope), [scope]);
  // Один раз на странице: панель создаётся при каждом открытии; null — ширина ещё неизвестна
  const compact = useMediaMatch(BELOW_MD);
  const [allFiltersOpen, setAllFiltersOpen] = useState(false);
  // Новое открытие — новый экземпляр панели со свежим черновиком
  const [allFiltersSession, setAllFiltersSession] = useState(0);
  const openAllFilters = () => {
    setAllFiltersSession((n) => n + 1);
    setAllFiltersOpen(true);
  };
  const { goToPage } = listing;
  // Номер страницы заменяет список — к началу страницы (market-docs/44)
  const openPage = useCallback(
    (page: number) => {
      goToPage(page);
      scrollToTop();
    },
    [goToPage],
  );
  const isEmpty = !listing.isLoading && listing.products.length === 0;
  const subcategories = useMemo(
    () => (category?.children ?? []).map((c) => ({ slug: c.slug, name: c.name })),
    [category],
  );

  return (
    <main className="bg-surface">
      <div
        className={`${PAGE_CONTAINER} pb-16 pt-6 max-md:pb-12 max-md:pt-5 max-vsm:pb-10 max-vsm:pt-4`}
      >
        <CatalogHeader
          category={category}
          title={displayName}
          total={shownTotal}
          inCatalog={scope.scope !== 'room'}
        />

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
            total={settledTotal}
            countProducts={countProducts}
            onOpenAllFilters={openAllFilters}
          />
        </div>

        <FilterChips
          query={query}
          meta={listing.filtersMeta}
          subcategories={subcategories}
          onQueryChange={setQuery}
          offsetClassName="pt-4 md:pt-6"
        />

        <p className="mt-4 empty:hidden md:hidden">
          <ProductCount total={shownTotal} />
        </p>

        <AllFiltersPanel
          key={allFiltersSession}
          open={allFiltersOpen}
          compact={compact === true}
          query={query}
          onQueryChange={setQuery}
          onClose={() => setAllFiltersOpen(false)}
          meta={listing.filtersMeta}
          total={settledTotal}
          countProducts={countProducts}
          subcategories={subcategories}
        />

        <div className="mt-6">
          {isEmpty ? (
            <EmptyResults
              query={query}
              sectionTotal={category?.products_count || null}
              onReset={() => setQuery(resetFilters(query))}
              onFirstPage={() => setQuery({ ...query, page: 1 })}
            />
          ) : (
            <ProductGrid
              products={listing.products}
              isLoading={listing.isLoading}
              isLoadingMore={listing.isLoadingMore}
              isRefreshing={listing.isRefreshing}
            />
          )}
        </div>

        {!isEmpty && !listing.isLoading && (
          <div className="mt-8 empty:hidden max-vsm:mt-6">
            <CatalogPagination
              query={query}
              pages={listing.pages}
              lastPage={listing.lastPage}
              hasMore={listing.hasMore}
              isLoadingMore={listing.isLoadingMore}
              onLoadMore={listing.loadMore}
              onPage={openPage}
            />
          </div>
        )}

        {category?.description ? (
          <div className="mt-10 max-vsm:mt-8">
            <CategoryDescription name={displayName} description={category.description} />
          </div>
        ) : null}
      </div>
    </main>
  );
}
