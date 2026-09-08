import { useState, useEffect, useLayoutEffect, useMemo } from 'react';
import { useSearchParams, Link, useNavigate } from 'react-router-dom';
import useSWR from 'swr';
import ProductCard from '../../components/ui/ProductCard';
import { api } from '../../lib/api';
import { useSSR } from '../../contexts/SSRContext';
import { useCartActions } from '../../hooks/useCartActions';
import { getCartQuantityForProduct } from '../../utils/cartProduct';
import { useCounters } from '../../hooks/useCounters';
import { useRegion } from '../../hooks/useRegion';
import { useWishlistAndCompare } from '../../hooks/useWishlistAndCompare';
import { getSearchKey } from '../../utils/ssr-to-swr';
import {
  getSortSelectValue,
  parseSortSelectValue,
  SORT_OPTIONS,
  CATALOG_PRODUCTS_PER_PAGE,
} from '../../lib/catalog-sort';
import { normalizeListProduct } from '../../utils/cartProduct';
import type { Product } from '../../lib/api';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import { ChevronLeft, ChevronRight, Search as SearchIcon, X } from 'lucide-react';

export default function Search() {
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  const ssrData = useSSR();
  const { cart, addProductToCart, changeProductQuantity } = useCartActions();
  const { refreshWishlistCount, refreshCompareCount } = useCounters();
  const { region } = useRegion();

  // Используем SSR данные если они есть
  const initialSearchQuery = ssrData?.search?.query || '';
  const initialProducts = ssrData?.search?.products || null;

  const [searchQuery, setSearchQuery] = useState(initialSearchQuery);
  const [products, setProducts] = useState<Product[]>(initialProducts?.data || []);
  const [isLoading, setIsLoading] = useState(!initialProducts);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  // Используем централизованный хук для wishlist и compare
  const {
    wishlistProductIds: favorites,
    compareProductIds: compareList,
    mutateWishlist,
    mutateCompare,
  } = useWishlistAndCompare();

  // Синхронизируем состояние с SSR данными
  useLayoutEffect(() => {
    if (initialProducts && initialSearchQuery) {
      setProducts(initialProducts.data || []);
      setTotalPages(initialProducts.meta?.last_page || 1);
      setTotal(initialProducts.meta?.total || 0);
      setIsLoading(false);
    }
  }, [initialProducts, initialSearchQuery]);

  // Инициализация из URL параметров
  useEffect(() => {
    const q = searchParams.get('q') || '';
    const page = parseInt(searchParams.get('page') || '1', 10);
    setSearchQuery(q);
    setCurrentPage(page);
  }, [searchParams]);

  usePageSeo({
    title: buildTitle('Поиск'),
    description: 'Результаты поиска в интернет-магазине Светофор-Мебель.',
    image: '/logo.png',
    canonical_url: window.location.href,
    robots: 'noindex, follow',
    open_graph_title: buildTitle('Поиск'),
    locale: 'ru_RU',
  });

  // Параметры для SWR/API из URL. sort_by и sort_order — как в API, ключ кэша уникален при смене сортировки.
  const searchParamsForSWR = useMemo(() => {
    const q = searchParams.get('q');
    if (!q || q.trim().length < 2) return null;

    const page = parseInt(searchParams.get('page') || '1', 10);
    const sortBy = searchParams.get('sort') || 'created_at';
    const sortOrder = (searchParams.get('order') || 'desc') as 'asc' | 'desc';

    return {
      query: q,
      page,
      sort_by: sortBy,
      sort_order: sortOrder,
      region_id: region?.id,
    };
  }, [searchParams, region?.id]);

  // Используем SWR для загрузки результатов поиска
  const swrKey = searchParamsForSWR
    ? getSearchKey(searchParamsForSWR.query, searchParamsForSWR)
    : null;
  const shouldUseSSRSearch =
    initialProducts &&
    initialSearchQuery === searchParamsForSWR?.query &&
    searchParamsForSWR &&
    searchParamsForSWR.page === (initialProducts.meta?.current_page || 1) &&
    searchParamsForSWR.sort_by === 'created_at' &&
    searchParamsForSWR.sort_order === 'desc';

  // Всегда передаём ключ SWR при наличии searchParamsForSWR, чтобы кэш обновлялся (revalidateInterval/revalidateOnFocus).
  // При SSR используем fallbackData — данные показываются сразу, SWR ревалидирует в фоне.
  const { data: searchData, isLoading: isLoadingSearch } = useSWR(
    swrKey ? swrKey : null,
    swrKey
      ? () =>
          api.products.search(searchParamsForSWR!.query, {
            per_page: CATALOG_PRODUCTS_PER_PAGE,
            page: searchParamsForSWR!.page,
            sort_by: searchParamsForSWR!.sort_by,
            sort_order: searchParamsForSWR!.sort_order,
            region_id: searchParamsForSWR!.region_id,
          })
      : null,
    {
      fallbackData: shouldUseSSRSearch ? initialProducts : undefined,
      revalidateOnMount: !shouldUseSSRSearch,
      revalidateIfStale: true,
    },
  );

  // Обновляем состояние при изменении данных
  useEffect(() => {
    if (!searchParamsForSWR) {
      setProducts([]);
      setTotal(0);
      setIsLoading(false);
      return;
    }

    if (searchData) {
      const data = (searchData.data || []).map((p) => normalizeListProduct(p));
      const meta = searchData.meta || {};
      const total = meta.total ?? 0;
      const lastPage = meta.last_page ?? 1;

      setProducts(data);
      setTotalPages(lastPage);
      setTotal(total);
      setIsLoading(false);
    } else if (initialProducts && shouldUseSSRSearch) {
      setProducts(initialProducts.data || []);
      setTotalPages(initialProducts.meta?.last_page || 1);
      setTotal(initialProducts.meta?.total || 0);
      setIsLoading(false);
    } else if (isLoadingSearch) {
      setIsLoading(true);
    }

    // Обновляем локальное состояние параметров
    if (searchParamsForSWR) {
      setCurrentPage(searchParamsForSWR.page);
    }
  }, [searchData, initialProducts, shouldUseSSRSearch, isLoadingSearch, searchParamsForSWR]);

  // Wishlist и compare загружаются через централизованный хук useWishlistAndCompare

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim().length >= 2) {
      const newParams = new URLSearchParams();
      newParams.set('q', searchQuery.trim());
      newParams.set('page', '1');
      setSearchParams(newParams);
    }
  };

  const handlePageChange = (page: number) => {
    const newParams = new URLSearchParams(searchParams);
    newParams.set('page', page.toString());
    setSearchParams(newParams);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleSortChange = (selectValue: string) => {
    const { sortBy, sortOrder } = parseSortSelectValue(selectValue);
    const newParams = new URLSearchParams(searchParams);
    newParams.set('sort', sortBy);
    newParams.set('order', sortOrder);
    newParams.set('page', '1');
    setSearchParams(newParams);
  };

  const handleAddToCart = async (productId: number) => {
    const product = products.find((p) => p.id === productId);
    if (product) await addProductToCart(product, 1);
  };

  const updateCartQuantityByProduct = async (productId: number, delta: number) => {
    const product = products.find((p) => p.id === productId);
    if (!product) return;
    await changeProductQuantity(product, delta);
  };

  const getProductIdForWishlist = (product: Product): number => {
    return product.id;
  };

  const getProductIdForCompare = (product: Product): number => {
    if (product.is_variable && !product.is_variant && product.first_available_variant_id) {
      return product.first_available_variant_id;
    }
    return product.id;
  };

  const toggleFavorite = async (product: Product) => {
    try {
      const productIdToAdd = getProductIdForWishlist(product);

      if (!productIdToAdd || productIdToAdd === 0) {
        console.error('Invalid product ID for wishlist:', product);
        return;
      }

      const response = await api.wishlist.toggle(productIdToAdd);

      // Оптимистично обновляем SWR кеш
      await mutateWishlist(async (current: any) => {
        const wishlistItems = current?.data || [];
        if (response.in_wishlist) {
          return { data: [...wishlistItems, { product_id: productIdToAdd }] };
        } else {
          return {
            data: wishlistItems.filter(
              (item: any) => (item.product_id || item.product?.id) !== productIdToAdd,
            ),
          };
        }
      }, false);

      await refreshWishlistCount();
    } catch (error) {
      console.error('Failed to toggle favorite:', error);
      mutateWishlist();
    }
  };

  const toggleCompare = async (product: Product) => {
    try {
      const productIdToAdd = getProductIdForCompare(product);

      const isInCompare = compareList.includes(productIdToAdd);

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
        await mutateCompare();
      }

      await refreshCompareCount();
    } catch (error: any) {
      console.error('Failed to toggle compare:', error);
      mutateCompare();
      if (error.status === 422) {
        alert(error.data?.message || 'Не удалось добавить товар в сравнение.');
      }
    }
  };

  const query = searchParams.get('q') || '';

  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-7xl mx-auto px-4 py-6 md:py-12">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-xs md:text-sm mb-4">
          <Link to="/" className="text-gray-600 hover:text-red-600">
            Главная
          </Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <span className="text-gray-900">Поиск</span>
        </div>

        {/* Search Header */}
        <div className="mb-6 md:mb-8">
          <h1 className="text-2xl md:text-4xl font-bold mb-4">Результаты поиска</h1>

          {/* Search Input */}
          <form onSubmit={handleSearch} className="max-w-2xl">
            <div className="relative">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Поиск товаров..."
                className="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-full text-sm md:text-base focus:outline-none focus:border-red-600 transition-colors bg-white"
              />
              <SearchIcon className="size-[1em] absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => {
                    setSearchQuery('');
                    navigate('/search');
                  }}
                  className="absolute right-12 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                >
                  <X className="size-[1em] text-lg" />
                </button>
              )}
              <button
                type="submit"
                className="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center text-white bg-red-600 rounded-full hover:bg-red-700 transition-colors"
              >
                <SearchIcon className="size-[1em] text-lg" />
              </button>
            </div>
          </form>

          {query && total > 0 && (
            <p className="text-gray-600 text-sm md:text-base mt-4">
              По запросу <span className="font-semibold text-gray-900">"{query}"</span> найдено:{' '}
              <span className="font-semibold">{total}</span>{' '}
              {total === 1 ? 'товар' : total < 5 ? 'товара' : 'товаров'}
            </p>
          )}
          {query && total === 0 && !isLoading && (
            <p className="text-gray-600 text-sm md:text-base mt-4">
              По запросу <span className="font-semibold text-gray-900">"{query}"</span> ничего не
              найдено
            </p>
          )}
        </div>

        {/* Results */}
        {!query || query.trim().length < 2 ? (
          <div className="text-center py-12">
            <SearchIcon className="size-[1em] text-6xl text-gray-300 mb-4" />
            <p className="text-gray-500 text-lg mb-2">Введите запрос для поиска</p>
            <p className="text-gray-400 text-sm">Минимум 2 символа</p>
          </div>
        ) : isLoading ? (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5">
            {[...Array(8)].map((_, i) => (
              <div key={i} className="bg-gray-200 rounded-2xl h-96 animate-pulse" />
            ))}
          </div>
        ) : products.length === 0 ? (
          <div className="text-center py-12">
            <SearchIcon className="size-[1em] text-6xl text-gray-300 mb-4" />
            <p className="text-gray-500 text-lg mb-2">Ничего не найдено</p>
            <p className="text-gray-400 text-sm">
              Попробуйте изменить запрос или использовать другие ключевые слова
            </p>
          </div>
        ) : (
          <>
            {/* Sort */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
              <p className="text-gray-600 text-sm">
                Найдено товаров: <span className="font-semibold">{total}</span>
              </p>
              <select
                value={getSortSelectValue(searchParams)}
                onChange={(e) => handleSortChange(e.target.value)}
                className="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-lg text-sm pr-8"
              >
                {SORT_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>

            {/* Products Grid */}
            <div
              className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5"
              data-product-shop
            >
              {products.map((product) => (
                <ProductCard
                  key={product.id}
                  product={product}
                  onAddToCart={handleAddToCart}
                  onIncreaseCart={(productId) => updateCartQuantityByProduct(productId, 1)}
                  onDecreaseCart={(productId) => updateCartQuantityByProduct(productId, -1)}
                  onToggleFavorite={toggleFavorite}
                  onToggleCompare={toggleCompare}
                  cartQuantity={getCartQuantityForProduct(cart?.items, product)}
                  isFavorite={favorites.includes(getProductIdForWishlist(product))}
                  isInCompare={compareList.includes(getProductIdForCompare(product))}
                />
              ))}
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex items-center justify-center gap-2 mt-8 md:mt-12">
                <button
                  onClick={() => handlePageChange(currentPage - 1)}
                  disabled={currentPage === 1}
                  className="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center border border-gray-300 rounded-lg hover:border-red-600 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                >
                  <ChevronLeft className="size-[1em] text-sm md:text-base" />
                </button>
                {(() => {
                  const pages: number[] = [];
                  const maxPages = 5;
                  let startPage = Math.max(1, currentPage - Math.floor(maxPages / 2));
                  const endPage = Math.min(totalPages, startPage + maxPages - 1);

                  if (endPage - startPage < maxPages - 1) {
                    startPage = Math.max(1, endPage - maxPages + 1);
                  }

                  for (let i = startPage; i <= endPage; i++) {
                    pages.push(i);
                  }

                  return pages.map((page) => (
                    <button
                      key={page}
                      onClick={() => handlePageChange(page)}
                      className={`w-8 h-8 md:w-10 md:h-10 flex items-center justify-center rounded-lg font-medium cursor-pointer text-sm md:text-base ${
                        currentPage === page
                          ? 'bg-red-600 text-white'
                          : 'border border-gray-300 hover:border-red-600'
                      }`}
                    >
                      {page}
                    </button>
                  ));
                })()}
                <button
                  onClick={() => handlePageChange(currentPage + 1)}
                  disabled={currentPage === totalPages}
                  className="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center border border-gray-300 rounded-lg hover:border-red-600 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                >
                  <ChevronRight className="size-[1em] text-sm md:text-base" />
                </button>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
