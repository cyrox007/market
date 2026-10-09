import { memo, useEffect, useMemo, useState, useTransition, useCallback } from 'react';
import ProductCardConnected from '../ui/ProductCardConnected';
import type { Product } from '../../lib/api';
import {
  collectBundleCategoryChips,
  filterBundleProductsByCategory,
  sortBundleProductsWithUnavailableLast,
} from '../../utils/bundleCategoryFilter';

type ProductBundleSectionProps = {
  products: Product[];
};

function ProductBundleSection({ products }: ProductBundleSectionProps) {
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
            <ProductCardConnected key={item.id} product={item} priority={index < 8} />
          ))}
        </div>
      )}
    </div>
  );
}

export default memo(ProductBundleSection);
