import type { Product } from '../../../lib/api';
import { CATALOG_PER_PAGE } from '../../../lib/catalog-query';
import { cn } from '../../../lib/cn';
import ProductCardConnected from '../../../components/ui/ProductCardConnected';

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

function Skeleton() {
  return (
    <div className="animate-pulse motion-reduce:animate-none">
      <div className="aspect-square rounded-btn bg-surface-grey" />
      <div className="mt-3 h-4 w-5/6 rounded bg-surface-grey" />
      <div className="mt-2 h-5 w-24 rounded bg-surface-grey" />
    </div>
  );
}

const skeletons = (count: number) =>
  Array.from({ length: count }, (_, i) => <Skeleton key={`skeleton-${i}`} />);

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
