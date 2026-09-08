import { memo, useEffect, useMemo, useState, useTransition, useCallback } from 'react';
import ProductCard from '../ui/ProductCard';
import type { Product } from '../../lib/api';
import {
  collectBundleCategoryChips,
  filterBundleProductsByCategory,
  sortBundleProductsWithUnavailableLast,
} from '../../utils/bundleCategoryFilter';

type ProductBundleSectionProps = {
  products: Product[];
  cartQuantityByProductId: Record<number, number>;
  onAddToCart: (productId: number) => void | Promise<void>;
  onIncreaseCart: (productId: number) => void | Promise<void>;
  onDecreaseCart: (productId: number) => void | Promise<void>;
  onToggleFavorite: (product: Product) => void | Promise<void>;
  onToggleCompare: (product: Product) => void | Promise<void>;
  isFavorite: (product: Product) => boolean;
  isInCompare: (product: Product) => boolean;
  onPrefetch?: (slug: string) => void;
};

function ProductBundleSection({
  products,
  cartQuantityByProductId,
  onAddToCart,
  onIncreaseCart,
  onDecreaseCart,
  onToggleFavorite,
  onToggleCompare,
  isFavorite,
  isInCompare,
  onPrefetch,
}: ProductBundleSectionProps) {
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [, startTransition] = useTransition();

  const categoryChips = useMemo(() => collectBundleCategoryChips(products), [products]);

  useEffect(() => {
    if (selectedCategoryId !== null && !categoryChips.some((c) => c.id === selectedCategoryId)) {
      setSelectedCategoryId(null);
    }
  }, [categoryChips, selectedCategoryId]);

  const filteredProducts = useMemo(() => {
    const filtered = filterBundleProductsByCategory(products, selectedCategoryId);
    return sortBundleProductsWithUnavailableLast(filtered);
  }, [products, selectedCategoryId]);

  const handleCategorySelect = useCallback((categoryId: number | null) => {
    startTransition(() => {
      setSelectedCategoryId(categoryId);
    });
  }, []);

  if (products.length === 0) {
    return null;
  }

  return (
    <div>
      <h2 className="text-2xl font-bold mb-6">Набор / комплект</h2>

      {categoryChips.length > 0 && (
        <nav
          className="flex flex-wrap gap-2 md:gap-3 mb-6 md:mb-8"
          aria-label="Категории товаров в наборе"
        >
          <button
            type="button"
            onClick={() => handleCategorySelect(null)}
            className={`px-4 py-2 rounded-lg text-sm md:text-base transition-colors cursor-pointer whitespace-nowrap ${
              selectedCategoryId === null
                ? 'bg-red-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-red-600 hover:text-white'
            }`}
          >
            Все
          </button>
          {categoryChips.map((cat) => (
            <button
              key={cat.id}
              type="button"
              onClick={() => handleCategorySelect(cat.id)}
              className={`px-4 py-2 rounded-lg text-sm md:text-base transition-colors cursor-pointer whitespace-nowrap ${
                selectedCategoryId === cat.id
                  ? 'bg-red-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-red-600 hover:text-white'
              }`}
            >
              {cat.name}
            </button>
          ))}
        </nav>
      )}

      {filteredProducts.length === 0 ? (
        <p className="text-gray-500 text-center py-8">Нет товаров в выбранной категории</p>
      ) : (
        <div
          className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5"
          data-product-shop
        >
          {filteredProducts.map((item, index) => (
            <ProductCard
              key={item.id}
              product={item}
              onMouseEnter={onPrefetch ? () => onPrefetch(item.slug) : undefined}
              onAddToCart={onAddToCart}
              onIncreaseCart={onIncreaseCart}
              onDecreaseCart={onDecreaseCart}
              onToggleFavorite={onToggleFavorite}
              onToggleCompare={onToggleCompare}
              cartQuantity={cartQuantityByProductId[item.id] ?? 0}
              isFavorite={isFavorite(item)}
              isInCompare={isInCompare(item)}
              priority={index < 8}
            />
          ))}
        </div>
      )}
    </div>
  );
}

export default memo(ProductBundleSection);
