import { useState, useEffect, useLayoutEffect, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import useSWR from 'swr';
import { isSheetHistoryEntry, SearchInput } from '../../components/ui/primitives';
import SortControl from '../../components/catalog/SortControl';
import { useMediaQuery } from '../../hooks/useMediaQuery';
import { api } from '../../lib/api';
import { useSSR } from '../../contexts/ssr-context';
import { useRegion } from '../../hooks/useRegion';
import { getSearchKey } from '../../utils/ssr-to-swr';
import { normalizeListProduct } from '../../utils/cartProduct';
import type { Product } from '../../lib/api';
import { usePageSeo } from '../../hooks/usePageSeo';
import { SEARCH_SEO } from './seo';
import {
  CATALOG_PER_PAGE,
  CATALOG_SORT_OPTIONS,
  DEFAULT_SORT,
  searchSort,
  withSort,
  type CatalogSort,
} from '../../lib/catalog-query';
import { BELOW_MD, PAGE_CONTAINER } from '../../lib/layout';
import { scrollToTop } from '../../lib/motion';
import CategoryBreadcrumbs from '../../components/catalog/CategoryBreadcrumbs';
import ProductCount from '../../components/catalog/ProductCount';
import EmptyState from '../../components/catalog/EmptyState';
import PageLinks from '../../components/catalog/PageLinks';
import ProductGrid from '../../components/catalog/ProductGrid';

export default function Search() {
  const [searchParams, setSearchParams] = useSearchParams();
  const ssrData = useSSR();
  const { region, locality, locationReady } = useRegion();
  // Сортировка ниже 980 — шторкой снизу, как в каталоге
  const compact = useMediaQuery(BELOW_MD);

  // Используем SSR данные если они есть
  const initialSearchQuery = ssrData?.search?.query || '';
  const initialProducts = ssrData?.search?.products || null;

  const [searchQuery, setSearchQuery] = useState(initialSearchQuery);
  const [products, setProducts] = useState<Product[]>(initialProducts?.data || []);
  const [isLoading, setIsLoading] = useState(!initialProducts);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);

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

  usePageSeo(SEARCH_SEO);

  // Параметры для SWR/API из URL. sort_by и sort_order — как в API, ключ кэша уникален при смене сортировки.
  const searchParamsForSWR = useMemo(() => {
    const q = searchParams.get('q');
    if (!q || q.trim().length < 2) return null;

    const page = parseInt(searchParams.get('page') || '1', 10);
    const { sort, sort_by, sort_order } = searchSort(searchParams);

    return {
      query: q,
      page,
      sort,
      sort_by,
      sort_order,
      region_id: region?.id,
      directory_external_id: locality?.externalId,
    };
  }, [searchParams, region?.id, locality?.externalId]);

  // Используем SWR для загрузки результатов поиска
  const swrKey = searchParamsForSWR
    ? getSearchKey(searchParamsForSWR.query, searchParamsForSWR)
    : null;
  const shouldUseSSRSearch =
    initialProducts &&
    initialSearchQuery === searchParamsForSWR?.query &&
    searchParamsForSWR &&
    searchParamsForSWR.page === (initialProducts.meta?.current_page || 1) &&
    searchParamsForSWR.sort === (ssrData?.search?.sort ?? DEFAULT_SORT);

  // Всегда передаём ключ SWR при наличии searchParamsForSWR, чтобы кэш обновлялся (revalidateInterval/revalidateOnFocus).
  // При SSR используем fallbackData — данные показываются сразу, SWR ревалидирует в фоне.
  // Пока сохранённый пункт не прочитан — без запроса (ответ сервера, если есть, уже на экране)
  const { data: searchData, isLoading: isLoadingSearch } = useSWR(
    swrKey && (locationReady || shouldUseSSRSearch) ? swrKey : null,
    swrKey
      ? () =>
          api.products.search(searchParamsForSWR!.query, {
            per_page: CATALOG_PER_PAGE,
            page: searchParamsForSWR!.page,
            sort_by: searchParamsForSWR!.sort_by,
            sort_order: searchParamsForSWR!.sort_order,
            region_id: searchParamsForSWR!.region_id,
            directory_external_id: searchParamsForSWR!.directory_external_id,
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

  const submitSearch = (value: string) => {
    if (value.length >= 2) {
      const newParams = new URLSearchParams();
      newParams.set('q', value);
      newParams.set('page', '1');
      setSearchParams(newParams);
    }
  };

  const handlePageChange = (page: number) => {
    const newParams = new URLSearchParams(searchParams);
    newParams.set('page', page.toString());
    setSearchParams(newParams);
    scrollToTop();
  };

  const handleSortChange = (sort: CatalogSort) => {
    // Выбрали в шторке сортировки — адрес заменяет её запись в истории
    setSearchParams(withSort(searchParams, sort), { replace: isSheetHistoryEntry() });
  };

  const query = searchParams.get('q') || '';

  const hrefOf = (page: number) => {
    const next = new URLSearchParams(searchParams);
    if (page > 1) next.set('page', String(page));
    else next.delete('page');
    return `/search?${next.toString()}`;
  };
  const tooShort = query.trim().length < 2;

  // Вид — как у списка каталога (market-docs/52): крошки, заголовок с числом, сетка, номера
  return (
    <main className="bg-surface">
      <div
        className={`${PAGE_CONTAINER} pb-16 pt-6 max-md:pb-12 max-md:pt-5 max-vsm:pb-10 max-vsm:pt-4`}
      >
        <CategoryBreadcrumbs name="Поиск" inCatalog={false} />
        <div className="mt-7 flex flex-wrap items-baseline gap-x-3 gap-y-2 max-md:mt-5">
          <h1 className="text-24 font-bold text-ink">Результаты поиска</h1>
          {!tooShort && !isLoading && (
            <ProductCount total={total} className="font-medium md:text-14" />
          )}
        </div>

        {/* Тот же SearchInput, что в шапке (Figma 235:3090 «search») */}
        <SearchInput
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          onSearch={submitSearch}
          placeholder="Диван-кровать, шкаф-купе, матрас..."
          aria-label="Поиск по каталогу"
          wrapperClassName="mt-6 max-w-2xl"
        />

        {!tooShort && products.length > 0 && (
          <div className="mt-6">
            <SortControl
              options={CATALOG_SORT_OPTIONS}
              value={searchSort(searchParams).sort}
              asSheet={compact}
              onChange={handleSortChange}
            />
          </div>
        )}

        <div className="mt-6">
          {tooShort ? (
            <EmptyState title="Введите запрос для поиска" hint="Минимум 2 символа." />
          ) : !isLoading && products.length === 0 ? (
            <EmptyState
              title={`По запросу «${query}» ничего не нашлось`}
              hint="Попробуйте изменить запрос или поискать по другим словам."
            />
          ) : (
            <ProductGrid
              products={products}
              isLoading={isLoading}
              isLoadingMore={false}
              isRefreshing={false}
            />
          )}
        </div>

        {!tooShort && !isLoading && (
          <div className="mt-8">
            <PageLinks
              pages={{ from: currentPage, to: currentPage }}
              lastPage={totalPages}
              hrefOf={hrefOf}
              onPage={handlePageChange}
            />
          </div>
        )}
      </div>
    </main>
  );
}
