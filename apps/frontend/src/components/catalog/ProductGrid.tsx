import type { Product } from '../../lib/api';
import { CATALOG_PER_PAGE } from '../../lib/catalog-query';
import { cn } from '../../lib/cn';
import ProductCardConnected from '../ui/ProductCardConnected';
import ProductCardSkeleton from '../ui/ProductCardSkeleton';

interface ProductGridProps {
  products: Product[];
  isLoading: boolean;
  isLoadingMore: boolean;
  /** На экране прежний список, новый грузится */
  isRefreshing: boolean;
}

/** До 550 — одна колонка, дальше 2 / 3 / 4 (market-docs/44) */
const GRID =
  'grid grid-cols-1 gap-x-3 gap-y-6 vsm:grid-cols-2 sm:grid-cols-3 md:grid-cols-4 md:gap-x-6 md:gap-y-8';

const skeletons = (count: number) =>
  Array.from({ length: count }, (_, i) => <ProductCardSkeleton key={`skeleton-${i}`} />);

export default function ProductGrid({
  products,
  isLoading,
  isLoadingMore,
  isRefreshing,
}: ProductGridProps) {
  if (isLoading) {
    return (
      <div className={GRID} aria-busy>
        {skeletons(CATALOG_PER_PAGE)}
      </div>
    );
  }
  return (
    <div
      className={cn(GRID, 'transition-opacity', isRefreshing && 'opacity-60')}
      aria-busy={isRefreshing || isLoadingMore || undefined}
      data-product-shop
    >
      {products.map((product, index) => (
        <ProductCardConnected key={product.id} product={product} priority={index < 4} />
      ))}
      {isLoadingMore && skeletons(4)}
    </div>
  );
}
