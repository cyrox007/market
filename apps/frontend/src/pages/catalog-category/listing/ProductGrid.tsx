import type { Product } from '../../../lib/api';
import ProductCardConnected from '../../../components/ui/ProductCardConnected';

interface ProductGridProps {
  products: Product[];
  isLoading: boolean;
  isLoadingMore: boolean;
  hasMore: boolean;
  onLoadMore: () => void;
}

const GRID = 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5';

function Skeletons({ count }: { count: number }) {
  return (
    <div className={GRID}>
      {Array.from({ length: count }, (_, i) => (
        <div key={i} className="bg-gray-200 rounded-2xl h-96 animate-pulse" />
      ))}
    </div>
  );
}

/** Временная вёрстка до задачи 06 (сетка по макету, номера страниц) */
export default function ProductGrid({
  products,
  isLoading,
  isLoadingMore,
  hasMore,
  onLoadMore,
}: ProductGridProps) {
  if (isLoading) return <Skeletons count={8} />;
  if (products.length === 0) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-500 text-lg">Товары не найдены</p>
      </div>
    );
  }
  return (
    <>
      <div className={GRID} data-product-shop>
        {products.map((product, index) => (
          <ProductCardConnected key={product.id} product={product} priority={index < 8} />
        ))}
      </div>
      {hasMore && (
        <div className="flex flex-col items-center gap-4 py-8">
          {isLoadingMore ? (
            <Skeletons count={4} />
          ) : (
            <button
              type="button"
              onClick={onLoadMore}
              className="px-6 py-3 border-2 border-red-600 text-red-600 rounded-lg font-medium hover:bg-red-50 transition-colors"
            >
              Показать ещё
            </button>
          )}
        </div>
      )}
    </>
  );
}
