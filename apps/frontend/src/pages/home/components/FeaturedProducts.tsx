import useSWR from 'swr';
import { api } from '../../../lib/api';
import { useSSR } from '../../../contexts/ssr-context';
import ProductCardConnected from '../../../components/ui/ProductCardConnected';
import { useLocationParams } from '../../../hooks/useLocationParams';
import { getHomeProductsKey } from '../../../utils/ssr-to-swr';
import { cn } from '../../../lib/cn';
import { PAGE_CONTAINER } from '../../../lib/layout';

export default function FeaturedProducts() {
  const ssrData = useSSR();
  const initialFeaturedProducts = ssrData?.home?.featuredProducts || [];

  const { params, ready } = useLocationParams();
  const fromServer = initialFeaturedProducts.length > 0;

  // SSR не знает пункт покупателя: с пунктом ответ сервера перезапрашиваем
  const { data: featuredProductsData, isLoading: isLoadingFeatured } = useSWR(
    ready || fromServer
      ? getHomeProductsKey('featured', params.region_id, params.directory_external_id)
      : null,
    () => api.products.featured(params),
    {
      fallbackData: fromServer ? { data: initialFeaturedProducts } : undefined,
      revalidateOnMount: !fromServer || Boolean(params.directory_external_id),
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

  if (isLoading) {
    return (
      <section className="py-8 md:py-16">
        <div className={cn(PAGE_CONTAINER, 'space-y-16')}>
          {[...Array(3)].map((_, i) => (
            <div key={i}>
              <div className="mb-8 h-8 w-48 animate-pulse rounded-btn bg-surface-grey" />
              <div className="grid grid-cols-4 max-[1239.98px]:grid-cols-3 max-sm:grid-cols-2 max-vsm:grid-cols-1 gap-4">
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
    <section className="py-8 md:py-16 ">
      <div className={cn(PAGE_CONTAINER, 'space-y-16')}>
        {sections.map((section, sectionIndex) => {
          if (section.products.length === 0) return null;

          return (
            <div key={sectionIndex}>
              <div className="flex items-center justify-between mb-4">
                <h2 className="font-display text-32 vsm:text-24 font-bold text-ink">
                  {section.title}
                </h2>
                {/* <Link
                  to={section.link}
                  className="flex items-center gap-2 whitespace-nowrap text-14 font-medium text-brand-red outline-none transition-opacity hover:opacity-80 focus-visible:opacity-80"
                >
                  <span>Смотреть все</span>
                  <ArrowRight className="size-[1em]" />
                </Link> */}
              </div>

              <div
                className="grid grid-cols-4 max-[1239.98px]:grid-cols-3 max-sm:grid-cols-2 max-vsm:grid-cols-1 gap-4"
                data-product-shop
              >
                {section.products.slice(0, 8).map((product) => (
                  <ProductCardConnected key={product.id} product={product} />
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
}
