import type { Category, Product } from '../../../lib/api';
import { PAGE_CONTAINER } from '../../../lib/layout';
import CategoryBreadcrumbs from './CategoryBreadcrumbs';
import CategoryTiles from './CategoryTiles';
import CatalogProductCardV2 from './CatalogProductCardV2';

interface CategoryLandingV2Props {
  category: Category;
  isRooms?: boolean;
  regionName?: string | null;
  products: Product[];
  total: number;
  isLoadingProducts: boolean;
  onPrefetchCategory?: (slug: string) => void;
  onPrefetchProduct?: (slug: string) => void;
  onAddToCart?: (productId: number) => void | Promise<void>;
  onToggleFavorite?: (product: Product) => void | Promise<void>;
  onToggleCompare?: (product: Product) => void | Promise<void>;
  isFavorite?: (product: Product) => boolean;
  isInCompare?: (product: Product) => boolean;
}

function ProductSkeleton() {
  return (
    <div className="animate-pulse">
      <div className="aspect-[292/270] rounded-[12px] bg-surface-grey" />
      <div className="mt-3 h-4 w-5/6 rounded bg-surface-grey" />
      <div className="mt-2 h-5 w-24 rounded bg-surface-grey" />
    </div>
  );
}

export default function CategoryLandingV2({
  category,
  isRooms = false,
  regionName,
  products,
  total,
  isLoadingProducts,
  onPrefetchCategory,
  onPrefetchProduct,
  onAddToCart,
  onToggleFavorite,
  onToggleCompare,
  isFavorite,
  isInCompare,
}: CategoryLandingV2Props) {
  const children = category.children ?? [];
  const sectionName = category.name.toLocaleLowerCase('ru-RU');

  return (
    <main className="bg-surface">
      <div className={`${PAGE_CONTAINER} pb-16 pt-6 max-md:pb-12 max-md:pt-5 max-vsm:pb-10 max-vsm:pt-4`}>
        <CategoryBreadcrumbs name={category.name} isRooms={isRooms} />

        <h1 className="mt-7 font-display text-32 font-bold text-ink max-vsm:mt-5 max-vsm:text-24">
          {category.name}
        </h1>

        <section className="mt-8 max-vsm:mt-6" aria-label={`Подкатегории: ${category.name}`}>
          <CategoryTiles
            categories={children}
            isRooms={isRooms}
            onPrefetch={onPrefetchCategory}
          />
        </section>

        <section className="mt-10 max-vsm:mt-8">
          <div className="mb-6 flex items-end justify-between gap-4 max-vsm:mb-4">
            <h2 className="font-display text-32 font-bold text-ink max-vsm:text-24">
              Популярные {sectionName}
            </h2>
            {total > 0 ? (
              <span className="shrink-0 text-14 text-ink-secondary max-vsm:hidden">
                {total} товаров
              </span>
            ) : null}
          </div>

          {isLoadingProducts ? (
            <div className="grid grid-cols-4 gap-5 max-md:grid-cols-3 max-sm:grid-cols-2 max-vsm:gap-3">
              {Array.from({ length: 4 }).map((_, index) => (
                <ProductSkeleton key={index} />
              ))}
            </div>
          ) : products.length ? (
            <>
              <div className="grid grid-cols-4 gap-5 max-md:grid-cols-3 max-sm:grid-cols-2 max-vsm:gap-3">
                {products.slice(0, 4).map((product, index) => (
                  <CatalogProductCardV2
                    key={product.id}
                    product={product}
                    onPrefetch={() => onPrefetchProduct?.(product.slug)}
                    onAddToCart={onAddToCart}
                    onToggleFavorite={onToggleFavorite}
                    onToggleCompare={onToggleCompare}
                    isFavorite={isFavorite?.(product)}
                    isInCompare={isInCompare?.(product)}
                    priority={index < 4}
                  />
                ))}
              </div>
            </>
          ) : (
            <div className="rounded-card bg-surface-grey px-6 py-10 text-center text-16 text-ink-secondary">
              В этой категории пока нет товаров.
            </div>
          )}
        </section>

        {category.description ? (
          <section className="mt-9 rounded-card bg-surface-grey px-6 py-5 max-vsm:mt-7 max-vsm:px-4">
            <h2 className="text-18 font-semibold text-ink">
              {category.name}{regionName ? ` в ${regionName}` : ''}
            </h2>
            <p className="mt-2 whitespace-pre-line text-14 leading-relaxed text-ink-secondary">
              {category.description}
            </p>
          </section>
        ) : null}
      </div>
    </main>
  );
}
