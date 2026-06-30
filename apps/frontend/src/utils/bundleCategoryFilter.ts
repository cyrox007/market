import type { Product } from '../lib/api';

export type BundleCategoryChip = {
  id: number;
  name: string;
  slug: string;
};

/**
 * Уникальные категории из primary category и categories[] товаров набора.
 */
export function collectBundleCategoryChips(products: Product[]): BundleCategoryChip[] {
  const map = new Map<number, BundleCategoryChip>();

  for (const product of products) {
    if (product.category?.id) {
      map.set(product.category.id, {
        id: product.category.id,
        name: product.category.name,
        slug: product.category.slug,
      });
    }

    const categories = (product as Product & { categories?: BundleCategoryChip[] }).categories;
    if (categories?.length) {
      for (const cat of categories) {
        if (cat?.id) {
          map.set(cat.id, {
            id: cat.id,
            name: cat.name,
            slug: cat.slug,
          });
        }
      }
    }
  }

  return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name, 'ru'));
}

/** Доступен в регионе и в наличии (или backorder). */
export function isBundleProductAvailable(product: Product): boolean {
  return product.is_visible_in_region !== false && product.in_stock !== false;
}

/**
 * Сохраняет порядок из API внутри группы; недоступные — в конце.
 */
export function sortBundleProductsWithUnavailableLast(products: Product[]): Product[] {
  return products
    .map((product, index) => ({ product, index }))
    .sort((a, b) => {
      const aAvail = isBundleProductAvailable(a.product);
      const bAvail = isBundleProductAvailable(b.product);
      if (aAvail !== bAvail) {
        return aAvail ? -1 : 1;
      }
      return a.index - b.index;
    })
    .map(({ product }) => product);
}

/**
 * Фильтр по выбранной категории (null = все).
 */
export function filterBundleProductsByCategory(
  products: Product[],
  selectedCategoryId: number | null,
): Product[] {
  if (selectedCategoryId === null) {
    return products;
  }

  return products.filter((product) => {
    if (product.category?.id === selectedCategoryId) {
      return true;
    }

    const categories = (product as Product & { categories?: BundleCategoryChip[] }).categories;
    return categories?.some((c) => c.id === selectedCategoryId) ?? false;
  });
}
