import { useEffect } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import useSWR from 'swr';
import { ChevronRight } from 'lucide-react';
import { api } from '../../lib/api';
import { useSSR } from '../../contexts/ssr-context';
import { usePrefetchCategory } from '../../hooks/usePrefetchCategory';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import { PAGE_CONTAINER } from '../../lib/layout';
import CategoryTiles from '../catalog-category/components/CategoryTiles';

const CATEGORIES_KEY = '/api/categories';
const HOME_COLLECTION_FILTERS = new Set(['new', 'featured', 'sale']);

export default function Catalog() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const ssrData = useSSR();
  const initialCategories = ssrData?.home?.categories || [];
  const prefetchCategory = usePrefetchCategory();

  usePageSeo({
    title: buildTitle('Каталог'),
    description:
      'Широкий выбор мебели для дома и офиса. Диваны, кровати, шкафы, столы, стулья и другие товары.',
    image: '/logo.png',
    robots: 'index, follow',
    open_graph_title: buildTitle('Каталог'),
    locale: 'ru_RU',
  });

  useEffect(() => {
    const filter = searchParams.get('filter');
    if (filter && HOME_COLLECTION_FILTERS.has(filter)) {
      navigate(`/collections/${filter}`, { replace: true });
    }
  }, [searchParams, navigate]);

  const { data: categoriesData, isLoading: isLoadingCategories } = useSWR(
    CATEGORIES_KEY,
    () => api.categories.list(),
    {
      fallbackData: initialCategories.length > 0 ? { data: initialCategories } : undefined,
      revalidateOnMount: initialCategories.length === 0,
      revalidateIfStale: true,
      revalidateOnFocus: false,
      dedupingInterval: 60_000,
      revalidateInterval: 300_000,
    },
  );

  const categories = categoriesData?.data ?? [];

  return (
    <main className="bg-surface">
      <div
        className={`${PAGE_CONTAINER} pb-16 pt-6 max-md:pb-12 max-md:pt-5 max-vsm:pb-10 max-vsm:pt-4`}
      >
        <nav
          aria-label="Хлебные крошки"
          className="flex items-center gap-2 text-14 text-ink-secondary max-vsm:gap-1.5 max-vsm:text-12"
        >
          <Link to="/" className="transition-colors hover:text-brand-green">
            Главная
          </Link>
          <ChevronRight className="size-4 max-vsm:size-3.5" aria-hidden />
          <span>Каталог</span>
        </nav>

        <h1 className="mt-7 font-display text-32 font-bold leading-none text-ink max-md:mt-6 max-vsm:mt-5 max-vsm:text-24">
          Каталог
        </h1>

        <section className="mt-8 max-md:mt-7 max-vsm:mt-6" aria-label="Категории каталога">
          {isLoadingCategories && !categories.length ? (
            <div className="grid grid-cols-2 gap-x-5 gap-y-6 vsm:grid-cols-4 md:grid-cols-6 max-vsm:gap-x-3 max-vsm:gap-y-5">
              {Array.from({ length: 12 }).map((_, index) => (
                <div key={index} className="animate-pulse">
                  <div className="aspect-[188/174] rounded-[18px] bg-surface-grey" />
                  <div className="mt-2 h-4 w-4/5 rounded bg-surface-grey" />
                </div>
              ))}
            </div>
          ) : (
            <CategoryTiles categories={categories} onPrefetch={prefetchCategory} />
          )}
        </section>
      </div>
    </main>
  );
}
