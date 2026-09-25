import { useState, useEffect, useLayoutEffect, useMemo, useRef, useCallback } from 'react';
import { useParams, useLocation, useSearchParams } from 'react-router-dom';
import useSWR from 'swr';
import useSWRInfinite from 'swr/infinite';
import CategoryLandingV2 from './components/CategoryLandingV2';
import CategoryLeafView from './components/CategoryLeafView';
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
  CATALOG_PRODUCTS_PER_PAGE,
} from '../../lib/catalog-sort';
import { normalizeListProduct } from '../../utils/cartProduct';
import {
  getCategoryFragment,
  setCategoryFragmentFromCategory,
} from '../../lib/category-fragment-cache';
import type { Category, Product, FiltersMeta } from '../../lib/api';

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
    <CategoryLeafView
      name={displayName}
      isRooms={isRooms}
      filters={filtersMetaDisplay}
      priceRange={priceRange as [number, number]}
      selectedColors={selectedColors}
      selectedSizes={selectedSizes}
      selectedAttributes={selectedAttributes}
      filtersOpen={isMobileFilterOpen}
      products={products}
      total={total}
      totalLoaded={pagesData != null}
      sortValue={getSortSelectValue(searchParams)}
      isLoadingProducts={isLoadingProducts}
      isLoadingMore={isLoadingMore}
      hasMore={hasMore}
      loadMoreRef={loadMoreRef}
      onOpenFilters={() => setIsMobileFilterOpen(true)}
      onCloseFilters={() => setIsMobileFilterOpen(false)}
      onPriceRangeChange={setPriceRange}
      onApplyPrice={handlePriceFilter}
      onColorToggle={handleColorToggle}
      onSizeToggle={handleSizeToggle}
      onAttributeToggle={handleAttributeToggle}
      onResetFilters={handleResetFilters}
      onSortChange={handleSortChange}
      onLoadMore={loadMore}
      onPrefetchProduct={prefetchProduct}
      onAddToCart={handleAddToCart}
      onIncreaseCart={(productId) => updateCartQuantityByProduct(productId, 1)}
      onDecreaseCart={(productId) => updateCartQuantityByProduct(productId, -1)}
      onToggleFavorite={toggleFavorite}
      onToggleCompare={toggleCompare}
      getCartQuantity={getCartQty}
      isFavorite={(product) => favorites.includes(getProductIdForWishlist(product))}
      isInCompare={(product) => compareList.includes(getProductIdForCompare(product))}
    />
  );
}
