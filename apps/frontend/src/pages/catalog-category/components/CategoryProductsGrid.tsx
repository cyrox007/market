import type { RefObject } from 'react';
import ProductCard from '../../../components/ui/ProductCard';
import type { Product } from '../../../lib/api';
import { SORT_OPTIONS } from '../../../lib/catalog-sort';

interface CategoryProductsGridProps {
  products: Product[];
  total: number;
  totalLoaded: boolean;
  sortValue: string;
  isLoading: boolean;
  isLoadingMore: boolean;
  hasMore: boolean;
  loadMoreRef: RefObject<HTMLDivElement | null>;
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

function ProductGridSkeleton({ count = 8 }: { count?: number }) {
  return (
    <div className="grid grid-cols-4 gap-5 max-md:grid-cols-3 max-sm:grid-cols-2 max-vsm:gap-3">
      {Array.from({ length: count }).map((_, index) => (
        <div key={index} className="h-96 animate-pulse rounded-card bg-surface-grey" />
      ))}
    </div>
  );
}

export default function CategoryProductsGrid({
  products,
  total,
  totalLoaded,
  sortValue,
  isLoading,
  isLoadingMore,
  hasMore,
  loadMoreRef,
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
}: CategoryProductsGridProps) {
  return (
    <section className="min-w-0 flex-1" aria-label="Товары">
      <div className="mb-6 flex items-center justify-between gap-4 max-vsm:items-stretch">
        <p className="text-14 text-ink-secondary max-vsm:hidden">
          Найдено товаров: {totalLoaded ? total : '—'}
        </p>
        <label className="ml-auto max-vsm:w-full">
          <span className="sr-only">Сортировка</span>
          <select
            value={sortValue}
            onChange={(event) => onSortChange(event.target.value)}
            className="h-11 rounded-pill border border-surface-border bg-surface px-4 pr-10 text-14 text-ink outline-none max-vsm:w-full"
          >
            {SORT_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>
      </div>

      {isLoading ? (
        <ProductGridSkeleton />
      ) : products.length === 0 ? (
        <div className="rounded-card bg-surface-grey px-6 py-14 text-center">
          <p className="text-18 font-semibold text-ink">Товары не найдены</p>
          <p className="mt-2 text-14 text-ink-secondary">
            Попробуйте изменить или сбросить фильтры.
          </p>
        </div>
      ) : (
        <>
          <div
            className="grid grid-cols-4 gap-5 max-md:grid-cols-3 max-sm:grid-cols-2 max-vsm:gap-3"
            data-product-shop
          >
            {products.map((product, index) => (
              <ProductCard
                key={product.id}
                product={product}
                onMouseEnter={() => onPrefetchProduct?.(product.slug)}
                onAddToCart={onAddToCart}
                onIncreaseCart={onIncreaseCart}
                onDecreaseCart={onDecreaseCart}
                onToggleFavorite={onToggleFavorite}
                onToggleCompare={onToggleCompare}
                cartQuantity={getCartQuantity(product)}
                isFavorite={isFavorite(product)}
                isInCompare={isInCompare(product)}
                priority={index < 8}
              />
            ))}
          </div>

          {hasMore ? (
            <div ref={loadMoreRef} className="py-8">
              {isLoadingMore ? (
                <ProductGridSkeleton count={4} />
              ) : (
                <div className="flex justify-center">
                  <button
                    type="button"
                    onClick={onLoadMore}
                    className="h-11 rounded-pill border border-ink px-6 text-14 font-medium text-ink hover:bg-ink hover:text-ink-inverse"
                  >
                    Загрузить ещё
                  </button>
                </div>
              )}
            </div>
          ) : null}
        </>
      )}
    </section>
  );
}
