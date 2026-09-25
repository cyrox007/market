import type { RefObject } from 'react';
import { ListFilter } from 'lucide-react';
import type { FiltersMeta, Product } from '../../../lib/api';
import { PAGE_CONTAINER } from '../../../lib/layout';
import CategoryBreadcrumbs from './CategoryBreadcrumbs';
import CategoryFiltersPanel from './CategoryFiltersPanel';
import CategoryProductsGrid from './CategoryProductsGrid';

interface CategoryLeafViewProps {
  name: string;
  isRooms?: boolean;
  filters: FiltersMeta | null;
  priceRange: [number, number];
  selectedColors: string[];
  selectedSizes: string[];
  selectedAttributes: Record<string, string[]>;
  filtersOpen: boolean;
  products: Product[];
  total: number;
  totalLoaded: boolean;
  sortValue: string;
  isLoadingProducts: boolean;
  isLoadingMore: boolean;
  hasMore: boolean;
  loadMoreRef: RefObject<HTMLDivElement | null>;
  onOpenFilters: () => void;
  onCloseFilters: () => void;
  onPriceRangeChange: (range: [number, number]) => void;
  onApplyPrice: () => void;
  onColorToggle: (slug: string) => void;
  onSizeToggle: (value: string) => void;
  onAttributeToggle: (attributeSlug: string, valueSlug: string) => void;
  onResetFilters: () => void;
  onSortChange: (value: string) => void;
  onLoadMore: () => void;
  onPrefetchProduct?: (slug: string) => void;
  onAddToCart: (productId: number) => void | Promise<void>;
  onIncreaseCart: (productId: number) => void | Promise<void>;
  onDecreaseCart: (productId: number) => void | Promise<void>;
  onToggleFavorite: (product: Product) => void | Promise<void>;
  onToggleCompare: (product: Product) => void | Promise<void>;
  getCartQuantity: (product: Product) => number;
  isFavorite: (product: Product) => boolean;
  isInCompare: (product: Product) => boolean;
}

export default function CategoryLeafView({
  name,
  isRooms = false,
  filters,
  priceRange,
  selectedColors,
  selectedSizes,
  selectedAttributes,
  filtersOpen,
  products,
  total,
  totalLoaded,
  sortValue,
  isLoadingProducts,
  isLoadingMore,
  hasMore,
  loadMoreRef,
  onOpenFilters,
  onCloseFilters,
  onPriceRangeChange,
  onApplyPrice,
  onColorToggle,
  onSizeToggle,
  onAttributeToggle,
  onResetFilters,
  onSortChange,
  onLoadMore,
  onPrefetchProduct,
  onAddToCart,
  onIncreaseCart,
  onDecreaseCart,
  onToggleFavorite,
  onToggleCompare,
  getCartQuantity,
  isFavorite,
  isInCompare,
}: CategoryLeafViewProps) {
  return (
    <main className="min-h-screen bg-surface">
      <div className={`${PAGE_CONTAINER} pb-16 pt-6 max-vsm:px-4 max-vsm:pt-4`}>
        <CategoryBreadcrumbs name={name} isRooms={isRooms} />

        <div className="mt-7 flex items-center justify-between gap-4 max-vsm:mt-5">
          <h1 className="font-display text-32 font-bold text-ink max-vsm:text-24">{name}</h1>
          <button
            type="button"
            onClick={onOpenFilters}
            className="hidden h-11 items-center gap-2 rounded-pill bg-brand-yellow px-4 text-14 font-medium text-ink max-md:flex"
          >
            <ListFilter className="size-5" />
            Фильтры
          </button>
        </div>

        <div className="mt-8 flex gap-6 max-md:block max-vsm:mt-6">
          <CategoryFiltersPanel
            open={filtersOpen}
            filters={filters}
            priceRange={priceRange}
            selectedColors={selectedColors}
            selectedSizes={selectedSizes}
            selectedAttributes={selectedAttributes}
            onClose={onCloseFilters}
            onPriceRangeChange={onPriceRangeChange}
            onApplyPrice={onApplyPrice}
            onColorToggle={onColorToggle}
            onSizeToggle={onSizeToggle}
            onAttributeToggle={onAttributeToggle}
            onReset={onResetFilters}
          />

          <CategoryProductsGrid
            products={products}
            total={total}
            totalLoaded={totalLoaded}
            sortValue={sortValue}
            isLoading={isLoadingProducts}
            isLoadingMore={isLoadingMore}
            hasMore={hasMore}
            loadMoreRef={loadMoreRef}
            onSortChange={onSortChange}
            onLoadMore={onLoadMore}
            onPrefetchProduct={onPrefetchProduct}
            onAddToCart={onAddToCart}
            onIncreaseCart={onIncreaseCart}
            onDecreaseCart={onDecreaseCart}
            onToggleFavorite={onToggleFavorite}
            onToggleCompare={onToggleCompare}
            getCartQuantity={getCartQuantity}
            isFavorite={isFavorite}
            isInCompare={isInCompare}
          />
        </div>
      </div>
    </main>
  );
}
