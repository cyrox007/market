import { useMemo } from 'react';
import { Link, useParams } from 'react-router-dom';
import useSWR from 'swr';
import ProductCard from '../../components/ui/ProductCard';
import { api } from '../../lib/api';
import { useCartActions } from '../../hooks/useCartActions';
import { getCartQuantityForProduct } from '../../utils/cartProduct';
import { useCounters } from '../../hooks/useCounters';
import { useRegion } from '../../hooks/useRegion';
import { useWishlistAndCompare } from '../../hooks/useWishlistAndCompare';
import { usePrefetchProduct } from '../../hooks/usePrefetchProduct';
import type { Product } from '../../lib/api';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import { ChevronRight } from 'lucide-react';

const COLLECTION_TITLE_FALLBACK: Record<string, string> = {
  new: 'Новинки',
  featured: 'Популярное',
  sale: 'Акции',
  recommended: 'Рекомендуем к покупке',
};

function getProductIdForWishlist(product: Product): number {
  return product.id;
}

function getProductIdForCompare(product: Product): number {
  if (product.is_variable && !product.is_variant && product.first_available_variant_id) {
    return product.first_available_variant_id;
  }
  return product.id;
}

export default function CollectionPage() {
  const { slug } = useParams<{ slug: string }>();
  const { region, getRegionId } = useRegion();
  const regionId = getRegionId();
  const {
    cart,
    addProductToCart,
    changeProductQuantity,
    isLoading: isCartLoading,
  } = useCartActions();
  const { refreshWishlistCount, refreshCompareCount } = useCounters();
  const {
    wishlistProductIds: favorites,
    compareProductIds: compareList,
    mutateWishlist,
    mutateCompare,
  } = useWishlistAndCompare();
  const prefetchProduct = usePrefetchProduct();

  const swrKey = slug ? `/api/products/collections/${slug}?region=${regionId ?? ''}` : null;

  const { data, error, isLoading } = useSWR(
    swrKey,
    () => api.products.collection(slug!, regionId ? { region_id: regionId } : undefined),
    { revalidateOnFocus: false },
  );

  usePageSeo({
    title: buildTitle('Коллекции'),
    description: 'Коллекции мебели в интернет-магазине Светофор-Мебель.',
    image: '/logo.png',
    canonical_url: window.location.href,
    robots: 'index, follow',
    open_graph_title: buildTitle('Коллекции'),
    locale: 'ru_RU',
  });

  const products = useMemo(
    () => (data?.data ?? []).filter((p: Product) => p.is_visible_in_region !== false),
    [data?.data],
  );

  const title =
    data?.collection?.name ?? (slug ? COLLECTION_TITLE_FALLBACK[slug] : undefined) ?? 'Подборка';

  const notFound = Boolean(
    slug && !isLoading && (error as { status?: number } | undefined)?.status === 404,
  );

  const handleAddToCart = async (productId: number) => {
    const product = products.find((p) => p.id === productId);
    if (product) await addProductToCart(product, 1);
  };

  const updateCartQuantityByProduct = async (productId: number, delta: number) => {
    const product = products.find((p) => p.id === productId);
    if (product) await changeProductQuantity(product, delta);
  };

  const getCartQty = (product: Product) => {
    if (isCartLoading && !cart?.items?.length) return 0;
    return getCartQuantityForProduct(cart?.items, product);
  };

  const toggleFavorite = async (product: Product) => {
    try {
      await api.wishlist.toggle(getProductIdForWishlist(product));
      await mutateWishlist();
      await refreshWishlistCount();
    } catch (err) {
      console.error('Failed to toggle favorite:', err);
    }
  };

  const toggleCompare = async (product: Product) => {
    try {
      const productIdToAdd = getProductIdForCompare(product);
      if (compareList.includes(productIdToAdd)) {
        await api.compare.remove(productIdToAdd);
      } else {
        await api.compare.add(productIdToAdd);
      }
      await mutateCompare();
      await refreshCompareCount();
    } catch (err: unknown) {
      const e = err as { status?: number; data?: { message?: string } };
      if (e.status === 422) {
        alert(e.data?.message || 'Не удалось добавить товар в сравнение.');
      }
    }
  };

  if (!slug) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-7xl mx-auto px-4 py-12">
          <h1 className="text-2xl font-bold">Подборка не найдена</h1>
        </div>
      </div>
    );
  }

  if (notFound) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-7xl mx-auto px-4 py-12">
          <div className="flex items-center gap-2 text-sm mb-4">
            <Link to="/" className="text-gray-600 hover:text-red-600">
              Главная
            </Link>
            <ChevronRight className="size-[1em] text-gray-400" />
            <Link to="/catalog" className="text-gray-600 hover:text-red-600">
              Каталог
            </Link>
          </div>
          <h1 className="text-2xl font-bold mb-4">Подборка не найдена</h1>
          <Link to="/catalog" className="text-red-600 hover:text-red-700 font-medium">
            Перейти в каталог
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-7xl mx-auto px-4 py-6 md:py-12">
        <div className="flex items-center gap-2 text-xs md:text-sm mb-4">
          <Link to="/" className="text-gray-600 hover:text-red-600">
            Главная
          </Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <Link to="/catalog" className="text-gray-600 hover:text-red-600">
            Каталог
          </Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <span className="text-gray-900">{title}</span>
        </div>

        <h1 className="text-2xl md:text-4xl font-bold mb-2">{title}</h1>
        {!isLoading && (
          <p className="text-gray-600 text-sm md:text-base mb-6 md:mb-8">
            {products.length}{' '}
            {products.length === 1
              ? 'товар'
              : products.length > 1 && products.length < 5
                ? 'товара'
                : 'товаров'}
            {region?.name ? ` · ${region.name}` : ''}
          </p>
        )}

        {isLoading ? (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-6">
            {[...Array(8)].map((_, i) => (
              <div key={i} className="bg-gray-200 rounded-2xl h-96 animate-pulse" />
            ))}
          </div>
        ) : products.length === 0 ? (
          <div className="text-center py-16">
            <p className="text-gray-500 text-lg mb-6">В этой подборке пока нет товаров</p>
            <Link
              to="/catalog"
              className="inline-block bg-red-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors"
            >
              Перейти в каталог
            </Link>
          </div>
        ) : (
          <div
            className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-6"
            data-product-shop
          >
            {products.map((product, index) => (
              <ProductCard
                key={product.id}
                product={product}
                onMouseEnter={() => prefetchProduct(product.slug)}
                onAddToCart={handleAddToCart}
                onIncreaseCart={(productId) => updateCartQuantityByProduct(productId, 1)}
                onDecreaseCart={(productId) => updateCartQuantityByProduct(productId, -1)}
                onToggleFavorite={toggleFavorite}
                onToggleCompare={toggleCompare}
                cartQuantity={getCartQty(product)}
                isFavorite={favorites.includes(getProductIdForWishlist(product))}
                isInCompare={compareList.includes(getProductIdForCompare(product))}
                priority={index < 8}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
