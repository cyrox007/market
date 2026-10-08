import { useMemo } from 'react';
import { useParams, useLocation } from 'react-router-dom';
import CategoryLandingV2 from './components/CategoryLandingV2';
import CatalogListing from './listing/CatalogListing';
import { useCatalogCategory } from './hooks/useCatalogCategory';
import { useLandingProducts } from './hooks/useLandingProducts';
import { useSSR } from '../../contexts/ssr-context';
import { useRegion } from '../../hooks/useRegion';
import { usePageSeo } from '../../hooks/usePageSeo';
import { usePrefetchCategory } from '../../hooks/usePrefetchCategory';
import type { CatalogScope } from '../../lib/catalog-query';

export default function CatalogCategory() {
  // Адрес может быть вложенным (/catalog/gostinaia/gotovye-stenki) — категория в последнем сегменте
  const { category: firstSegment, '*': nestedPath } = useParams<{
    category: string;
    '*': string;
  }>();
  const slug = nestedPath?.split('/').filter(Boolean).pop() ?? firstSegment;
  // Одна страница на каталог и комнаты: источник данных выбираем по адресу
  const isRooms = useLocation().pathname.startsWith('/rooms');

  const ssrData = useSSR();
  const { region } = useRegion();
  const prefetchCategory = usePrefetchCategory();
  const { category, effectiveCategory, displayName, seo, notFound } = useCatalogCategory(
    slug,
    isRooms,
  );
  usePageSeo(seo);

  // Лендинг — только у родительских категорий с подкатегориями
  const isLanding = !isRooms && Boolean(effectiveCategory?.children?.length);
  const landing = useLandingProducts(isLanding && slug ? slug : null, region?.id);

  const scope = useMemo<CatalogScope>(
    () => ({ scope: isRooms ? 'room' : 'category', slug: slug ?? '', regionId: region?.id }),
    [isRooms, slug, region?.id],
  );

  const ssrListing = ssrData?.category;
  const initial =
    ssrListing?.products && ssrListing.productsParams
      ? { params: ssrListing.productsParams, response: ssrListing.products }
      : null;

  if (notFound) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-[1280px] mx-auto px-4 py-12">
          <h1 className="text-2xl font-bold">Категория не найдена</h1>
        </div>
      </div>
    );
  }

  if (isLanding && category) {
    return (
      <CategoryLandingV2
        category={category}
        regionName={region?.name}
        products={landing.products}
        isLoadingProducts={landing.isLoading}
        onPrefetchCategory={prefetchCategory}
      />
    );
  }

  return (
    <CatalogListing
      category={category}
      displayName={displayName}
      scope={scope}
      initial={initial}
      onPrefetchCategory={prefetchCategory}
    />
  );
}
