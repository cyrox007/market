import { useState, useEffect, useLayoutEffect, useMemo, useRef, useCallback } from 'react';
import { useParams, useLocation, Link, useSearchParams } from 'react-router-dom';
import useSWR from 'swr';
import useSWRInfinite from 'swr/infinite';
import ProductCard from '../../components/ui/ProductCard';
import CategoryLandingV2 from './components/CategoryLandingV2';
import { api } from '../../lib/api';
import { useSSR } from '../../contexts/SSRContext';
import { useCounters } from '../../hooks/useCounters';
import { useRegion } from '../../hooks/useRegion';
import { useWishlistAndCompare } from '../../hooks/useWishlistAndCompare';
import { useCartActions } from '../../hooks/useCartActions';
import { getCartQuantityForProduct } from '../../utils/cartProduct';
import { usePageSeo } from '../../hooks/usePageSeo';
import { usePrefetchCategory } from '../../hooks/usePrefetchCategory';
import { usePrefetchProduct } from '../../hooks/usePrefetchProduct';
import { getCategoryProductsKey, parseCategoryProductsKey } from '../../utils/ssr-to-swr';
import {
  getSortSelectValue,
  parseSortSelectValue,
  SORT_OPTIONS,
  CATALOG_PRODUCTS_PER_PAGE,
} from '../../lib/catalog-sort';
import { normalizeListProduct } from '../../utils/cartProduct';
import {
  getCategoryFragment,
  setCategoryFragmentFromCategory,
} from '../../lib/category-fragment-cache';
import type { Category, Product, FiltersMeta } from '../../lib/api';
import { ChevronRight, ListFilter, X } from 'lucide-react';

export default function CatalogCategory() {
  const { category: categorySlug } = useParams<{ category: string }>();
  // Одна страница на каталог и комнаты: источник данных выбираем по URL.
  const isRooms = useLocation().pathname.startsWith('/rooms');
  const source = isRooms ? api.rooms : api.categories;
  const [searchParams, setSearchParams] = useSearchParams();
  const ssrData = useSSR();
  const initialRegionId = ssrData?.region?.id;

  // SSR данные — только для текущей категории (slug совпадает с URL)
  const initialCategory = ssrData?.category?.category || null;
  const initialProducts = ssrData?.category?.products || null;

  const [category, setCategory] = useState<Category | null>(initialCategory);

  const [priceRange, setPriceRange] = useState([0, 100000]);
  const [filtersMeta, setFiltersMeta] = useState<FiltersMeta | null>(null);
  const filtersMetaStableRef = useRef<FiltersMeta | null>(null);
  const [selectedColors, setSelectedColors] = useState<string[]>([]);
  const [selectedSizes, setSelectedSizes] = useState<string[]>([]);
  const [selectedAttributes, setSelectedAttributes] = useState<Record<string, string[]>>({});
  const [isMobileFilterOpen, setIsMobileFilterOpen] = useState(false);
  const { refreshWishlistCount, refreshCompareCount } = useCounters();
  const { region } = useRegion();
  const {
    cart,
    addProductToCart,
    changeProductQuantity,
    isLoading: isCartLoading,
  } = useCartActions();

  // Используем централизованный хук для wishlist и compare
  const {
    wishlistProductIds: favorites,
    compareProductIds: compareList,
    mutateWishlist,
    mutateCompare,
  } = useWishlistAndCompare();
  const prefetchCategory = usePrefetchCategory();
  const prefetchProduct = usePrefetchProduct();

  const prevCategorySlugRef = useRef<string | undefined>(undefined);

  useLayoutEffect(() => {
    if (initialCategory && initialCategory.slug === categorySlug) {
      setCategory(initialCategory);
    }
    const prevSlug = prevCategorySlugRef.current;
    prevCategorySlugRef.current = categorySlug;
    if (prevSlug != null && categorySlug != null && prevSlug !== categorySlug) {
      setFiltersMeta(null);
      filtersMetaStableRef.current = null;
    }
  }, [initialCategory, categorySlug]);

  // Используем SWR для загрузки категории (keepPreviousData: показываем предыдущую категорию при смене, пока грузится новая)
  const {
    data: categoryData,
    error: categoryError,
    isLoading: isLoadingCategory,
  } = useSWR(
    categorySlug && (!initialCategory || initialCategory.slug !== categorySlug)
      ? `/api/${isRooms ? 'rooms' : 'categories'}/${categorySlug}`
      : null,
    () => source.get(categorySlug!),
    {
      fallbackData: initialCategory ? { category: initialCategory } : undefined,
      revalidateOnMount: !initialCategory,
      keepPreviousData: true,
    },
  );

  useEffect(() => {
    if (categoryData?.category) {
      setCategoryFragmentFromCategory(categoryData.category);
      setCategory((prev) => {
        if (prev?.id === categoryData.category.id) {
          return prev;
        }
        return categoryData.category;
      });
    }
  }, [categoryData, categorySlug]);

  // Сохраняем в фрагментный кэш при SSR/initial категории
  useEffect(() => {
    if (initialCategory && initialCategory.slug === categorySlug) {
      setCategoryFragmentFromCategory(initialCategory);
    }
  }, [initialCategory, categorySlug]);

  // Название и SEO: фрагментный кэш → category → categoryData → «Каталог» (никогда не показываем slug в заголовке)
  const fragment = categorySlug ? getCategoryFragment(categorySlug) : null;
  const effectiveCategory = category ?? categoryData?.category ?? null;
  const displayName = fragment?.name ?? effectiveCategory?.name ?? 'Каталог';
  const displaySeo = fragment?.seo ?? effectiveCategory?.seo ?? null;
  usePageSeo(displaySeo);

  // Стабильная строка поиска — избегаем новой ссылки searchParams на каждый рендер (Maximum update depth). — избегаем новой ссылки searchParams на каждый рендер (Maximum update depth).
  const searchString = searchParams.toString();

  // Парсим параметры для SWR/API из URL (без page — используется ленивая подгрузка по скроллу).
  const productsParams = useMemo(() => {
    if (!categorySlug) return null;

    const priceMin = searchParams.get('price_min')
      ? parseInt(searchParams.get('price_min')!, 10)
      : undefined;
    const priceMax = searchParams.get('price_max')
      ? parseInt(searchParams.get('price_max')!, 10)
      : undefined;
    const sortBy = searchParams.get('sort') || 'created_at';
    const sortOrder = (searchParams.get('order') || 'desc') as 'asc' | 'desc';
    const colorsParam = searchParams.get('colors')
      ? searchParams.get('colors')!.split(',').filter(Boolean)
      : [];
    const sizesParam = searchParams.get('sizes')
      ? searchParams.get('sizes')!.split(',').filter(Boolean)
      : [];

    const attributesParam: Record<string, string[]> = {};
    searchParams.forEach((value, key) => {
      if (key.startsWith('attr_')) {
        const slug = key.replace('attr_', '');
        const values = value.split(',').filter(Boolean);
        if (values.length) attributesParam[slug] = values;
      }
    });

    return {
      ...(isRooms ? { room_slug: categorySlug } : { category_slug: categorySlug }),
      price_min: priceMin,
      price_max: priceMax,
      sort_by: sortBy,
      sort_order: sortOrder,
      per_page: CATALOG_PRODUCTS_PER_PAGE,
      colors: colorsParam,
      sizes: sizesParam,
      attributes: attributesParam,
      region_id: region?.id,
    };
  }, [categorySlug, isRooms, searchString, region?.id]);

  // Базовые параметры запроса (без page) — каждая страница кэшируется отдельно (малый фрагмент); ключ включает регион и фильтры.
  const baseRequestOptions = useMemo(() => {
    if (!productsParams || !categorySlug) return null;
    const { colors, sizes, attributes, ...rest } = productsParams;
    return {
      ...rest,
      per_page: CATALOG_PRODUCTS_PER_PAGE,
      ...(colors.length > 0 && { colors }),
      ...(sizes.length > 0 && { sizes }),
      ...(Object.keys(attributes).length > 0 && { attributes }),
    };
  }, [productsParams, categorySlug]);

  const hasNonDefaultFilters = !!(
    productsParams &&
    (productsParams.price_min !== undefined ||
      productsParams.price_max !== undefined ||
      productsParams.colors.length > 0 ||
      productsParams.sizes.length > 0 ||
      Object.keys(productsParams.attributes).length > 0 ||
      productsParams.sort_by !== 'created_at' ||
      productsParams.sort_order !== 'desc')
  );

  const isSameRegionAsSSR = !initialRegionId || productsParams?.region_id === initialRegionId;
  const shouldUseSSRProducts = Boolean(
    initialProducts &&
    initialCategory?.slug === categorySlug &&
    productsParams &&
    !hasNonDefaultFilters &&
    isSameRegionAsSSR,
  );

  const firstPageKey =
    baseRequestOptions && categorySlug
      ? getCategoryProductsKey(
          categorySlug,
          { ...baseRequestOptions, page: 1 },
          isRooms ? 'room_slug' : 'category_slug',
        )
      : null;

  const infiniteFallbackData = useMemo(() => {
    if (!shouldUseSSRProducts || !initialProducts || !firstPageKey) return undefined;
    return [
      {
        data: initialProducts.data || [],
        current_page: initialProducts.meta?.current_page ?? 1,
        last_page: initialProducts.meta?.last_page ?? 1,
        per_page: initialProducts.meta?.per_page ?? 20,
        total: initialProducts.meta?.total ?? 0,
        meta: initialProducts.meta,
      },
    ];
  }, [shouldUseSSRProducts, initialProducts, firstPageKey]);

  const isLastPage = (
    page: {
      current_page?: number;
      last_page?: number;
      meta?: { current_page?: number; last_page?: number };
    } | null,
  ) => {
    if (!page) return false;
    const current = page.current_page ?? page.meta?.current_page;
    const last = page.last_page ?? page.meta?.last_page;
    return current != null && last != null && current >= last;
  };

  const {
    data: pagesData,
    setSize,
    isLoading: isLoadingInfinite,
    isValidating,
  } = useSWRInfinite(
    (
      pageIndex: number,
      previousPageData: {
        current_page?: number;
        last_page?: number;
        meta?: { current_page?: number; last_page?: number };
      } | null,
    ) => {
      if (!firstPageKey || !baseRequestOptions) return null;
      if (isLastPage(previousPageData)) return null;
      return getCategoryProductsKey(
        categorySlug!,
        { ...baseRequestOptions, page: pageIndex + 1 },
        isRooms ? 'room_slug' : 'category_slug',
      );
    },
    async (key: string) => {
      const params = parseCategoryProductsKey(key);
      if (!params) throw new Error('Invalid key');
      return api.products.list(params);
    },
    {
      // Всегда обновляем 1-ю страницу: SSR-кэш мог отдать is_variable:false (баг API при per_page=20).
      revalidateFirstPage: true,
      revalidateOnFocus: false,
      dedupingInterval: 10_000,
      fallbackData: infiniteFallbackData,
    },
  );

  const previousPagesDataRef = useRef<typeof pagesData>(undefined);
  // Не перезаписываем буфер "пустыми" страницами во время промежуточной ревалидации,
  // чтобы карточки не исчезали при смене региона/ключа.
  if (pagesData != null) {
    const firstPageItemsCount = pagesData[0]?.data?.length ?? 0;
    if (pagesData.length > 0 && firstPageItemsCount > 0) {
      previousPagesDataRef.current = pagesData;
    }
  }
  const displayPagesData =
    pagesData != null && pagesData.length > 0 ? pagesData : previousPagesDataRef.current;

  const productsDataFirstPage = displayPagesData?.[0];
  const products = useMemo(() => {
    if (!displayPagesData?.length) return [];
    const merged = displayPagesData.flatMap((page) => page.data || []);
    return merged
      .filter((p: Product) => p.is_visible_in_region !== false)
      .map((p) => normalizeListProduct(p));
  }, [displayPagesData]);

  const total =
    productsDataFirstPage != null
      ? (productsDataFirstPage.total ??
        (productsDataFirstPage as { meta?: { total?: number } }).meta?.total ??
        0)
      : 0;
  const getLastPage = (page: unknown) => {
    const p = page as { last_page?: number; meta?: { last_page?: number } } | undefined;
    return p?.last_page ?? p?.meta?.last_page ?? 1;
  };
  const hasMore =
    pagesData != null && pagesData.length > 0 && pagesData.length < getLastPage(pagesData[0]);
  const isLoadingProducts = isLoadingInfinite && pagesData == null;
  const isLoadingMore = isValidating && (pagesData?.length ?? 0) > 0;

  useEffect(() => {
    const filters = productsDataFirstPage?.meta?.filters;
    if (filters) {
      filtersMetaStableRef.current = filters;
      setFiltersMeta(filters);
      if (!productsParams?.price_min || !productsParams?.price_max) {
        setPriceRange([
          productsParams?.price_min ?? filters.price?.min ?? 0,
          productsParams?.price_max ?? filters.price?.max ?? 100000,
        ]);
      }
    }
  }, [productsDataFirstPage?.meta?.filters, productsParams?.price_min, productsParams?.price_max]);

  useEffect(() => {
    const filters = initialProducts?.meta?.filters;
    if (filters && shouldUseSSRProducts) {
      filtersMetaStableRef.current = filters;
      setFiltersMeta(filters);
    }
  }, [initialProducts, shouldUseSSRProducts]);

  const filtersMetaDisplay = filtersMeta ?? filtersMetaStableRef.current;

  useEffect(() => {
    if (!productsParams) return;
    if (productsParams.price_min !== undefined)
      setPriceRange((prev) => [productsParams.price_min!, prev[1]]);
    if (productsParams.price_max !== undefined)
      setPriceRange((prev) => [prev[0], productsParams.price_max!]);
    setSelectedColors(productsParams.colors);
    setSelectedSizes(productsParams.sizes);
    setSelectedAttributes(productsParams.attributes);
  }, [productsParams]);

  const loadMoreRef = useRef<HTMLDivElement>(null);
  const loadMore = useCallback(() => {
    if (hasMore && !isLoadingMore) setSize((s) => s + 1);
  }, [hasMore, isLoadingMore, setSize]);

  const loadMoreCbRef = useRef(loadMore);
  loadMoreCbRef.current = loadMore;

  useEffect(() => {
    if (!hasMore) return;
    const el = loadMoreRef.current;
    if (!el) return;
    const io = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting) loadMoreCbRef.current();
      },
      { rootMargin: '200px', threshold: 0.1 },
    );
    io.observe(el);
    return () => io.disconnect();
  }, [hasMore]);

  // Слушаем изменения региона - SWR автоматически обновит данные при изменении ключа
  // Ключ SWR зависит от region?.id, поэтому при изменении региона данные перезагрузятся
  // Wishlist и compare загружаются через централизованный хук useWishlistAndCompare

  const handleSortChange = (selectValue: string) => {
    const { sortBy, sortOrder } = parseSortSelectValue(selectValue);
    const newParams = new URLSearchParams(searchParams);
    newParams.set('sort', sortBy);
    newParams.set('order', sortOrder);
    setSearchParams(newParams);
  };

  const handlePriceFilter = () => {
    const newParams = new URLSearchParams(searchParams);
    if (priceRange[0] > 0) {
      newParams.set('price_min', priceRange[0].toString());
    } else {
      newParams.delete('price_min');
    }
    if (priceRange[1] < 100000) {
      newParams.set('price_max', priceRange[1].toString());
    } else {
      newParams.delete('price_max');
    }
    setSearchParams(newParams);
    setIsMobileFilterOpen(false);
  };

  const updateArrayParam = (key: string, values: string[]) => {
    const newParams = new URLSearchParams(searchParams);
    if (values.length) {
      newParams.set(key, values.join(','));
    } else {
      newParams.delete(key);
    }
    setSearchParams(newParams);
  };

  const handleColorToggle = (slug: string) => {
    const next = selectedColors.includes(slug)
      ? selectedColors.filter((c) => c !== slug)
      : [...selectedColors, slug];
    setSelectedColors(next);
    updateArrayParam('colors', next);
  };

  const handleSizeToggle = (value: string) => {
    const next = selectedSizes.includes(value)
      ? selectedSizes.filter((s) => s !== value)
      : [...selectedSizes, value];
    setSelectedSizes(next);
    updateArrayParam('sizes', next);
  };

  const handleAttributeToggle = (attrSlug: string, valueSlug: string) => {
    const current = selectedAttributes[attrSlug] || [];
    const nextValues = current.includes(valueSlug)
      ? current.filter((v) => v !== valueSlug)
      : [...current, valueSlug];

    const updated = { ...selectedAttributes, [attrSlug]: nextValues };
    if (nextValues.length === 0) {
      delete updated[attrSlug];
    }
    setSelectedAttributes(updated);
    updateArrayParam(`attr_${attrSlug}`, nextValues);
  };

  const handleResetFilters = () => {
    const newParams = new URLSearchParams();
    const sort = searchParams.get('sort');
    const order = searchParams.get('order');
    if (sort) newParams.set('sort', sort);
    if (order) newParams.set('order', order);
    setSearchParams(newParams);
    setPriceRange([0, 100000]);
    setSelectedColors([]);
    setSelectedSizes([]);
    setSelectedAttributes({});
    setIsMobileFilterOpen(false);
  };

  const findProductById = useCallback(
    (productId: number) => products.find((p) => p.id === productId),
    [products],
  );

  const handleAddToCart = async (productId: number) => {
    const product = findProductById(productId);
    if (product) await addProductToCart(product, 1);
  };

  const getCartQty = useCallback(
    (product: Product) => {
      if (isCartLoading && !cart?.items?.length) return 0;
      return getCartQuantityForProduct(cart?.items, product);
    },
    [cart?.items, isCartLoading],
  );

  const updateCartQuantityByProduct = async (productId: number, delta: number) => {
    const product = findProductById(productId);
    if (!product) return;
    await changeProductQuantity(product, delta);
  };

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

      if (!productIdToAdd || productIdToAdd === 0) {
        console.error('Invalid product ID for wishlist:', product);
        return;
      }

      await api.wishlist.toggle(productIdToAdd);
      // Обновляем кэш SWR через mutate
      await mutateWishlist();
      await refreshWishlistCount();
    } catch (error) {
      console.error('Failed to toggle favorite:', error);
      console.error('Product:', product);
    }
  };

  const toggleCompare = async (product: Product) => {
    try {
      // Для сравнения используем первый доступный вариант для вариативных товаров
      const productIdToAdd = getProductIdForCompare(product);

      const isInCompare = compareList.includes(productIdToAdd);
      if (isInCompare) {
        await api.compare.remove(productIdToAdd);
      } else {
        await api.compare.add(productIdToAdd);
      }
      // Обновляем кэш SWR через mutate
      await mutateCompare();
      // Небольшая задержка, чтобы дать время API обновиться
      await new Promise((resolve) => setTimeout(resolve, 100));
      await refreshCompareCount();
    } catch (error: any) {
      console.error('Failed to toggle compare:', error);
      if (error.status === 422) {
        alert(error.data?.message || 'Не удалось добавить товар в сравнение.');
      }
    }
  };

  // Показываем «Категория не найдена» только когда загрузка категории завершена и категория не найдена (404 или пустой ответ)
  const categoryNotFound =
    categorySlug &&
    !isLoadingCategory &&
    !category &&
    (categoryError != null || (categoryData != null && !categoryData.category));

  if (categoryNotFound) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-[1280px] mx-auto px-4 py-12">
          <h1 className="text-2xl font-bold">Категория не найдена</h1>
        </div>
      </div>
    );
  }

  if (category?.children?.length) {
    return (
      <CategoryLandingV2
        category={category}
        isRooms={isRooms}
        regionName={region?.name}
        products={products}
        total={total}
        isLoadingProducts={isLoadingProducts}
        onPrefetchCategory={prefetchCategory}
        onPrefetchProduct={prefetchProduct}
        onAddToCart={handleAddToCart}
        onToggleFavorite={toggleFavorite}
        onToggleCompare={toggleCompare}
        isFavorite={(product) => favorites.includes(getProductIdForWishlist(product))}
        isInCompare={(product) => compareList.includes(getProductIdForCompare(product))}
      />
    );
  }

  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-[1280px] mx-auto px-4 py-4 md:py-6">
        <div className="flex items-start justify-between gap-6 mb-4 md:mb-6">
          <div className="flex-1">
            {/* Breadcrumbs */}
            <div className="flex items-center gap-2 text-sm mb-2">
              <Link to="/" className="text-gray-600 hover:text-red-600">
                Главная
              </Link>
              <ChevronRight className="size-[1em] text-gray-400" />
              <Link to="/catalog" className="text-gray-600 hover:text-red-600">
                Каталог
              </Link>
              <ChevronRight className="size-[1em] text-gray-400" />
              <span className="text-gray-900">{displayName}</span>
            </div>
            <h1 className="text-2xl md:text-3xl font-bold">{displayName}</h1>
          </div>
        </div>

        {/* Subcategories — показываем только после загрузки категории */}
        {category?.children && category.children.length > 0 && (
          <div className="flex flex-wrap gap-2 md:gap-3 mb-6 md:mb-8">
            {category.children.map((subcat) => (
              <Link
                key={subcat.id}
                to={`/catalog/${subcat.slug}`}
                className="px-4 py-2 rounded-lg text-sm md:text-base transition-colors cursor-pointer whitespace-nowrap bg-gray-100 text-gray-700 hover:bg-red-600 hover:text-white"
                onMouseEnter={() => prefetchCategory(subcat.slug)}
                onFocus={() => prefetchCategory(subcat.slug)}
              >
                {subcat.name}
              </Link>
            ))}
          </div>
        )}

        {/* Mobile Filter Button */}
        <button
          onClick={() => setIsMobileFilterOpen(!isMobileFilterOpen)}
          className="lg:hidden w-full mb-4 bg-red-600 text-white py-3 rounded-lg font-medium flex items-center justify-center gap-2 whitespace-nowrap"
        >
          <ListFilter className="size-[1em]" />
          Фильтры
        </button>

        {/* Filters and Products */}
        <div className="flex gap-6">
          {/* Sidebar Filters */}
          <div
            className={`${isMobileFilterOpen ? 'fixed inset-0 z-50 bg-white overflow-y-auto' : 'hidden'} lg:block lg:w-52 lg:flex-shrink-0`}
          >
            <div className="lg:hidden flex items-center justify-between p-4 border-b">
              <h3 className="font-semibold text-lg">Фильтры</h3>
              <button
                onClick={() => setIsMobileFilterOpen(false)}
                className="w-8 h-8 flex items-center justify-center"
              >
                <X className="size-[1em] text-2xl" />
              </button>
            </div>

            <div className="bg-white border-0 lg:border lg:border-gray-200 rounded-none lg:rounded-2xl p-4 lg:p-5 lg:sticky lg:top-4">
              <h3 className="font-semibold text-base mb-4 hidden lg:block">Фильтры</h3>

              {!filtersMetaDisplay ? (
                /* Скелетоны фильтров пока meta не загружена */
                <div className="animate-pulse space-y-5">
                  <div>
                    <div className="h-4 bg-gray-200 rounded w-12 mb-2" />
                    <div className="flex gap-2 mb-2">
                      <div className="flex-1 h-9 bg-gray-200 rounded-lg" />
                      <div className="flex-1 h-9 bg-gray-200 rounded-lg" />
                    </div>
                    <div className="h-9 bg-gray-200 rounded-lg" />
                  </div>
                  <div>
                    <div className="h-4 bg-gray-200 rounded w-14 mb-2" />
                    <div className="flex flex-wrap gap-2">
                      {[...Array(6)].map((_, i) => (
                        <div key={i} className="w-8 h-8 rounded-full bg-gray-200" />
                      ))}
                    </div>
                  </div>
                  <div>
                    <div className="h-4 bg-gray-200 rounded w-16 mb-2" />
                    <div className="flex flex-wrap gap-2">
                      {[...Array(4)].map((_, i) => (
                        <div key={i} className="h-9 w-16 bg-gray-200 rounded-lg" />
                      ))}
                    </div>
                  </div>
                  {[...Array(2)].map((_, i) => (
                    <div key={i}>
                      <div className="h-4 bg-gray-200 rounded w-24 mb-2" />
                      <div className="space-y-2">
                        {[...Array(3)].map((_, j) => (
                          <div key={j} className="flex items-center gap-2">
                            <div className="h-4 w-4 rounded bg-gray-200" />
                            <div className="h-4 bg-gray-200 rounded flex-1 max-w-[80%]" />
                          </div>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <>
                  {/* Price Range */}
                  <div className="mb-5">
                    <label className="block text-sm font-medium mb-2">Цена</label>
                    <div className="flex gap-2 mb-2">
                      <input
                        type="number"
                        value={priceRange[0]}
                        onChange={(e) => setPriceRange([+e.target.value, priceRange[1]])}
                        className="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm"
                        placeholder="От"
                      />
                      <input
                        type="number"
                        value={priceRange[1]}
                        onChange={(e) => setPriceRange([priceRange[0], +e.target.value])}
                        className="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm"
                        placeholder="До"
                      />
                    </div>
                    <button
                      onClick={handlePriceFilter}
                      className="w-full bg-red-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"
                    >
                      Применить
                    </button>
                  </div>

                  {/* Colors — все опции категории, недоступные (count === 0) серыми */}
                  {filtersMetaDisplay?.colors && filtersMetaDisplay.colors.length > 0 && (
                    <div className="mb-5">
                      <label className="block text-sm font-medium mb-2">Цвет</label>
                      <div className="flex flex-wrap gap-2">
                        {filtersMetaDisplay.colors.map((color, colorIndex) => {
                          const disabled = color.count !== undefined && color.count === 0;
                          const slug = color.slug || color.name || '';
                          return (
                            <button
                              key={`${color.slug || color.name || 'color'}-${colorIndex}`}
                              type="button"
                              onClick={() => !disabled && handleColorToggle(slug)}
                              disabled={disabled}
                              className={`w-8 h-8 rounded-full border-2 ${
                                disabled
                                  ? 'border-gray-200 opacity-50 cursor-not-allowed'
                                  : selectedColors.includes(slug)
                                    ? 'border-red-600 scale-110'
                                    : 'border-gray-300 hover:border-red-600'
                              }`}
                              style={{ backgroundColor: color.code || '#f5f5f5' }}
                              title={disabled ? 'Нет товаров' : color.name || ''}
                            />
                          );
                        })}
                      </div>
                    </div>
                  )}

                  {/* Sizes — все опции категории, недоступные (count === 0) серыми */}
                  {filtersMetaDisplay?.sizes && filtersMetaDisplay.sizes.length > 0 && (
                    <div className="mb-5">
                      <label className="block text-sm font-medium mb-2">Размер</label>
                      <div className="flex flex-wrap gap-2">
                        {filtersMetaDisplay.sizes.map((size, sizeIndex) => {
                          const disabled = size.count !== undefined && size.count === 0;
                          const value = size.value || '';
                          return (
                            <button
                              key={size.slug || size.value || `size-${sizeIndex}`}
                              type="button"
                              onClick={() => !disabled && handleSizeToggle(value)}
                              disabled={disabled}
                              className={`px-3 py-2 rounded-lg border text-sm font-medium whitespace-nowrap ${
                                disabled
                                  ? 'border-gray-200 text-gray-400 cursor-not-allowed opacity-60'
                                  : selectedSizes.includes(value)
                                    ? 'border-red-600 bg-red-50 text-red-600'
                                    : 'border-gray-300 hover:border-red-600'
                              }`}
                            >
                              {size.name || size.value}
                            </button>
                          );
                        })}
                      </div>
                    </div>
                  )}

                  {/* Attributes — все опции категории, недоступные (count === 0) серыми */}
                  {filtersMetaDisplay?.attributes &&
                    filtersMetaDisplay.attributes.length > 0 &&
                    filtersMetaDisplay.attributes.map((attr) => (
                      <div className="mb-5" key={attr.slug}>
                        <label className="block text-sm font-medium mb-2">{attr.name}</label>
                        <div className="space-y-2">
                          {attr.values.map((val) => {
                            const disabled = val.count !== undefined && val.count === 0;
                            return (
                              <label
                                key={`${attr.slug}-${val.slug}`}
                                className={`flex items-center gap-2 ${disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'}`}
                              >
                                <input
                                  type="checkbox"
                                  checked={(selectedAttributes[attr.slug] || []).includes(val.slug)}
                                  onChange={() =>
                                    !disabled && handleAttributeToggle(attr.slug, val.slug)
                                  }
                                  disabled={disabled}
                                  className="w-4 h-4 text-red-600 rounded disabled:opacity-50"
                                />
                                <span className="text-sm text-gray-700">{val.name}</span>
                              </label>
                            );
                          })}
                        </div>
                      </div>
                    ))}

                  {/* Сброс фильтров */}
                  <button
                    type="button"
                    onClick={handleResetFilters}
                    className="w-full mt-4 bg-red-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors flex items-center justify-center gap-2"
                  >
                    Сбросить фильтры
                  </button>
                </>
              )}
            </div>
          </div>

          {/* Products Grid */}
          <div className="flex-1">
            {/* Sort */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
              <p className="text-gray-600 text-sm">
                Найдено товаров: {pagesData != null ? total : '—'}
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

            {/* Products */}
            {isLoadingProducts ? (
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5">
                {[...Array(8)].map((_, i) => (
                  <div key={i} className="bg-gray-200 rounded-2xl h-96 animate-pulse" />
                ))}
              </div>
            ) : products.length === 0 ? (
              <div className="text-center py-12">
                <p className="text-gray-500 text-lg">Товары не найдены</p>
              </div>
            ) : (
              <>
                <div
                  className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5"
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
                {/* Подгрузка при прокрутке + кнопка «Загрузить ещё» */}
                {hasMore && (
                  <div ref={loadMoreRef} className="flex flex-col items-center gap-4 py-8">
                    {isLoadingMore ? (
                      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5 w-full">
                        {[...Array(4)].map((_, i) => (
                          <div key={i} className="bg-gray-200 rounded-2xl h-96 animate-pulse" />
                        ))}
                      </div>
                    ) : (
                      <button
                        type="button"
                        onClick={() => loadMore()}
                        className="px-6 py-3 border-2 border-red-600 text-red-600 rounded-lg font-medium hover:bg-red-50 transition-colors"
                      >
                        Загрузить ещё
                      </button>
                    )}
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
