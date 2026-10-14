import type { Category, Product } from '../../../lib/api';
import { PAGE_CONTAINER } from '../../../lib/layout';
import CategoryBreadcrumbs from './CategoryBreadcrumbs';
import CategoryDescription from './CategoryDescription';
import CategoryTiles from './CategoryTiles';
import ProductCardConnected from '../../../components/ui/ProductCardConnected';
import ProductCardSkeleton from '../../../components/ui/ProductCardSkeleton';

interface CategoryLandingV2Props {
  category: Category;
  products: Product[];
  isLoadingProducts: boolean;
  onPrefetchCategory?: (slug: string) => void;
}

export default function CategoryLandingV2({
  category,
  products,
  isLoadingProducts,
  onPrefetchCategory,
}: CategoryLandingV2Props) {
  const children = category.children ?? [];
  const sectionName = category.name.toLocaleLowerCase('ru-RU');

  return (
    <main className="bg-surface">
      <div
        className={`${PAGE_CONTAINER} pb-16 pt-6 max-md:pb-12 max-md:pt-5 max-vsm:pb-10 max-vsm:pt-4`}
      >
        <CategoryBreadcrumbs name={category.name} />

        <h1 className="mt-7 font-display text-32 font-bold leading-none text-ink max-md:mt-6 max-vsm:mt-5 max-vsm:text-24">
          {category.name}
        </h1>

        <section
          className="mt-8 max-md:mt-7 max-vsm:mt-6"
          aria-label={`Подкатегории: ${category.name}`}
        >
          <CategoryTiles categories={children} onPrefetch={onPrefetchCategory} />
        </section>

        <section className="mt-11 max-md:mt-10 max-vsm:mt-8">
          <h2 className="mb-7 font-display text-32 font-bold leading-none text-ink max-md:mb-6 max-vsm:mb-4 max-vsm:text-24">
            Популярные {sectionName}
          </h2>

          {isLoadingProducts ? (
            <div className="grid grid-cols-1 gap-x-3 gap-y-6 vsm:grid-cols-2 md:grid-cols-4 md:gap-5">
              {Array.from({ length: 4 }).map((_, index) => (
                <ProductCardSkeleton key={index} />
              ))}
            </div>
          ) : products.length ? (
            <div className="grid grid-cols-1 gap-x-3 gap-y-6 vsm:grid-cols-2 md:grid-cols-4 md:gap-5">
              {products.slice(0, 4).map((product, index) => (
                // Общая карточка: корзина, избранное, сравнение, префетч — внутри. market-docs/36
                <ProductCardConnected key={product.id} product={product} priority={index < 4} />
              ))}
            </div>
          ) : (
            <div className="rounded-card bg-surface-grey px-6 py-10 text-center text-16 text-ink-secondary max-vsm:px-4 max-vsm:py-8 max-vsm:text-14">
              В этой категории пока нет товаров.
            </div>
          )}
        </section>

        {category.description ? (
          <div className="mt-9 max-vsm:mt-8">
            <CategoryDescription name={category.name} description={category.description} />
          </div>
        ) : null}
      </div>
    </main>
  );
}
