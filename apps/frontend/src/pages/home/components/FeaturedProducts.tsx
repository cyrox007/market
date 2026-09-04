import useSWR from 'swr';
import { api } from '../../../lib/api';
import { useSSR } from '../../../contexts/SSRContext';
import type { Product } from '../../../lib/api';
import { Link } from 'react-router-dom';
import ProductCard from '../../../components/ui/ProductCard';
import { useCounters } from '../../../hooks/useCounters';
import { useWishlistAndCompare } from '../../../hooks/useWishlistAndCompare';
import { useRegion } from '../../../hooks/useRegion';
import { getHomeProductsKey } from '../../../utils/ssr-to-swr';
import { ArrowRight } from 'lucide-react';

export default function FeaturedProducts() {
  const ssrData = useSSR();
  const initialNewProducts = ssrData?.home?.newProducts || [];
  const initialFeaturedProducts = ssrData?.home?.featuredProducts || [];
  const initialSaleProducts = ssrData?.home?.saleProducts || [];

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
  const { data: newProductsData, isLoading: isLoadingNew } = useSWR(
    getHomeProductsKey('new', regionId),
    () => api.products.new(regionId ? { region_id: regionId } : undefined),
    {
      fallbackData: initialNewProducts.length > 0 ? { data: initialNewProducts } : undefined,
      revalidateOnMount: initialNewProducts.length === 0, // Ревалидируем только если нет SSR данных
      revalidateIfStale: true,
    },
  );

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

  const { data: saleProductsData, isLoading: isLoadingSale } = useSWR(
    getHomeProductsKey('sale', regionId),
    () => api.products.sale(regionId ? { region_id: regionId } : undefined),
    {
      fallbackData: initialSaleProducts.length > 0 ? { data: initialSaleProducts } : undefined,
      revalidateOnMount: initialSaleProducts.length === 0,
      revalidateIfStale: true,
    },
  );

  const newProducts = newProductsData?.data || [];
  const featuredProducts = featuredProductsData?.data || [];
  const saleProducts = saleProductsData?.data || [];
  const hasAnyData =
    newProducts.length > 0 || featuredProducts.length > 0 || saleProducts.length > 0;
  const isLoading = (isLoadingNew || isLoadingFeatured || isLoadingSale) && !hasAnyData;

  const sections = [
    {
      title: 'Новинки',
      products: newProducts,
      link: '/collections/new',
    },
    {
      title: 'Популярное',
      products: featuredProducts,
      link: '/collections/featured',
    },
    {
      title: 'Акции',
      products: saleProducts,
      link: '/collections/sale',
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
      <section className="px-4 lg:px-12 py-16 bg-gray-50">
        <div className="max-w-7xl mx-auto space-y-16">
          {[...Array(3)].map((_, i) => (
            <div key={i}>
              <div className="h-8 bg-gray-200 rounded w-48 mb-8 animate-pulse" />
              <div className="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-5">
                {[...Array(3)].map((_, j) => (
                  <div key={j} className="bg-white rounded-2xl overflow-hidden animate-pulse">
                    <div className="aspect-[4/3] bg-gray-200" />
                    <div className="p-5">
                      <div className="h-4 bg-gray-200 rounded w-20 mb-2" />
                      <div className="h-6 bg-gray-200 rounded w-3/4 mb-3" />
                      <div className="h-6 bg-gray-200 rounded w-24" />
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
    <section className="px-4 lg:px-12 py-16 bg-gray-50">
      <div className="max-w-7xl mx-auto space-y-16">
        {sections.map((section, sectionIndex) => {
          if (section.products.length === 0) return null;

          return (
            <div key={sectionIndex}>
              <div className="flex items-center justify-between mb-8">
                <h2 className="text-3xl font-bold text-gray-900">{section.title}</h2>
                <Link
                  to={section.link}
                  className="text-red-600 hover:text-red-700 font-semibold flex items-center gap-2 cursor-pointer whitespace-nowrap"
                >
                  <span>Смотреть все</span>
                  <ArrowRight className="size-[1em]" />
                </Link>
              </div>

              <div className="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-5" data-product-shop>
                {section.products.slice(0, 3).map((product) => (
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
