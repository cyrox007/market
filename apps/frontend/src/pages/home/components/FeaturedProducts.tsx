import useSWR from 'swr';
import { api } from '../../../lib/api';
import { useSSR } from '../../../contexts/ssr-context';
import type { Product } from '../../../lib/api';
import { Link } from 'react-router-dom';
import ProductCard from '../../../components/ui/ProductCard';
import { useCounters } from '../../../hooks/useCounters';
import { useWishlistAndCompare } from '../../../hooks/useWishlistAndCompare';
import { useRegion } from '../../../hooks/useRegion';
import { getHomeProductsKey } from '../../../utils/ssr-to-swr';
import { ArrowRight } from 'lucide-react';
import { cn } from '../../../lib/cn';
import { PAGE_CONTAINER } from '../../../lib/layout';

export default function FeaturedProducts() {
  const ssrData = useSSR();
  const initialFeaturedProducts = ssrData?.home?.featuredProducts || [];

  const { refreshWishlistCount, refreshCompareCount } = useCounters();
  const { getRegionId } = useRegion();
  const regionId = getRegionId();

  // Используем централизованный хук для wishlist и compare
  const {
    wishlistProductIds: favorites,
    compareProductIds: compareList,
    mutateWishlist,
    mutateCompare,
  } = useWishlistAndCompare();

  // Используем SWR для загрузки данных с fallback из SSR
  const { data: featuredProductsData, isLoading: isLoadingFeatured } = useSWR(
    getHomeProductsKey('featured', regionId),
    () => api.products.featured(regionId ? { region_id: regionId } : undefined),
    {
      fallbackData:
        initialFeaturedProducts.length > 0 ? { data: initialFeaturedProducts } : undefined,
      revalidateOnMount: initialFeaturedProducts.length === 0,
      revalidateIfStale: true,
    },
  );

  const featuredProducts = featuredProductsData?.data || [];
  const isLoading = isLoadingFeatured && featuredProducts.length === 0;

  // В макете главной одна секция; подробности — market-docs/23
  const sections = [
    {
      title: 'Хит продаж',
      products: featuredProducts,
      link: '/collections/featured',
    },
  ];

  // Вспомогательная функция для определения ID товара для избранного
  // Для избранного всегда используем родительский товар для вариативных товаров
  const getProductIdForWishlist = (product: Product): number => {
    // Для избранного всегда используем родительский товар (не вариант)
    return product.id;
  };

  // Вспомогательная функция для определения ID товара для сравнения
  // Для сравнения используем первый доступный вариант для вариативных товаров
  const getProductIdForCompare = (product: Product): number => {
    // Для вариативных товаров используем первый доступный вариант
    if (product.is_variable && !product.is_variant && product.first_available_variant_id) {
      return product.first_available_variant_id;
    }
    return product.id;
  };

  const toggleFavorite = async (product: Product) => {
    try {
      // Для избранного всегда используем родительский товар
      const productIdToAdd = getProductIdForWishlist(product);

      const response = await api.wishlist.toggle(productIdToAdd);

      // Оптимистично обновляем SWR кеш
      await mutateWishlist(async (current: any) => {
        const wishlistItems = current?.data || [];
        if (response.in_wishlist) {
          // Добавляем товар
          return { data: [...wishlistItems, { product_id: productIdToAdd }] };
        } else {
          // Удаляем товар
          return {
            data: wishlistItems.filter(
              (item: any) => (item.product_id || item.product?.id) !== productIdToAdd,
            ),
          };
        }
      }, false); // false = не ревалидировать сразу

      await refreshWishlistCount();
    } catch (error) {
      console.error('Failed to toggle favorite:', error);
      // Откатываем изменения при ошибке
      mutateWishlist();
    }
  };

  const toggleCompare = async (product: Product) => {
    try {
      // Для сравнения используем первый доступный вариант для вариативных товаров
      const productIdToAdd = getProductIdForCompare(product);

      const isInCompare = compareList.includes(productIdToAdd);

      // Оптимистично обновляем SWR кеш
      if (isInCompare) {
        await api.compare.remove(productIdToAdd);
        await mutateCompare(async (current: any) => {
          const products = current?.products || [];
          return {
            products: products.filter((p: Product) => p.id !== productIdToAdd),
          };
        }, false);
      } else {
        await api.compare.add(productIdToAdd);
        // Для добавления нужно загрузить данные товара, поэтому просто ревалидируем
        await mutateCompare();
      }

      await refreshCompareCount();
    } catch (error: any) {
      console.error('Failed to toggle compare:', error);
      // Откатываем изменения при ошибке
      mutateCompare();
      if (error.status === 422) {
        alert(error.data?.message || 'Не удалось добавить товар в сравнение.');
      }
    }
  };

  if (isLoading) {
    return (
      <section className="py-16">
        <div className={cn(PAGE_CONTAINER, 'space-y-16')}>
          {[...Array(3)].map((_, i) => (
            <div key={i}>
              <div className="mb-8 h-8 w-48 animate-pulse rounded-btn bg-surface-grey" />
              <div className="grid grid-cols-4 max-md:grid-cols-3 max-sm:grid-cols-2 max-vsm:grid-cols-1 gap-3 md:gap-5">
                {[...Array(8)].map((_, j) => (
                  <div key={j} className="flex flex-col gap-3">
                    <div className="aspect-square animate-pulse rounded-btn bg-surface-grey" />
                    <div className="flex flex-col gap-1.5 px-0.5">
                      <div className="h-5 w-3/4 animate-pulse rounded-btn bg-surface-grey" />
                      <div className="h-6 w-24 animate-pulse rounded-btn bg-surface-grey" />
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </section>
    );
  }

  return (
    <section className="py-16">
      <div className={cn(PAGE_CONTAINER, 'space-y-16')}>
        {sections.map((section, sectionIndex) => {
          if (section.products.length === 0) return null;

          return (
            <div key={sectionIndex}>
              <div className="flex items-center justify-between mb-8">
                <h2 className="font-display text-24 font-bold text-ink">{section.title}</h2>
                <Link
                  to={section.link}
                  className="flex items-center gap-2 whitespace-nowrap text-14 font-medium text-brand-red outline-none transition-opacity hover:opacity-80 focus-visible:opacity-80"
                >
                  <span>Смотреть все</span>
                  <ArrowRight className="size-[1em]" />
                </Link>
              </div>

              <div
                className="grid grid-cols-4 max-md:grid-cols-3 max-sm:grid-cols-2 max-vsm:grid-cols-1 gap-3 md:gap-5"
                data-product-shop
              >
                {section.products.slice(0, 8).map((product) => (
                  <ProductCard
                    key={product.id}
                    product={product}
                    onToggleFavorite={toggleFavorite}
                    onToggleCompare={toggleCompare}
                    isFavorite={favorites.includes(getProductIdForWishlist(product))}
                    isInCompare={compareList.includes(getProductIdForCompare(product))}
                    className="group"
                  />
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
}
