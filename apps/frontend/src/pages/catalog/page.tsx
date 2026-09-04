import { useEffect, useRef } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import useSWR from 'swr';
import { api } from '../../lib/api';
import { useSSR } from '../../contexts/SSRContext';
import { usePrefetchCategory } from '../../hooks/usePrefetchCategory';
import type { Category } from '../../lib/api';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import Icon from '../../components/ui/icons/Icon';
import {
  Archive,
  Armchair,
  Baby,
  BedDouble,
  Briefcase,
  ChevronRight,
  DoorOpen,
  Layers,
  Lightbulb,
  Palette,
  Sofa,
  TableIcon,
  Tv,
  Utensils,
} from 'lucide-react';

const CATEGORIES_KEY = '/api/categories';

function CategoryCard({
  category,
  index,
  prefetchCategory,
}: {
  category: Category;
  index: number;
  prefetchCategory: (slug: string) => void;
}) {
  const ref = useRef<HTMLAnchorElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const observer = new IntersectionObserver(
      (entries) => {
        const [entry] = entries;
        if (entry?.isIntersecting) {
          prefetchCategory(category.slug);
          observer.unobserve(entry.target);
        }
      },
      { rootMargin: '150px' },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, [category.slug, prefetchCategory]);

  usePageSeo({
    title: buildTitle('Каталог'),
    description:
      'Широкий выбор мебели для дома и офиса. Диваны, кровати, шкафы, столы, стулья и другие товары.',
    image: '/logo.png',
    canonical_url: window.location.href,
    robots: 'index, follow',
    open_graph_title: buildTitle('Каталог'),
    locale: 'ru_RU',
  });

  return (
    <Link
      ref={ref}
      to={`/catalog/${category.slug}`}
      className="bg-white border-2 border-gray-200 rounded-2xl p-4 md:p-6 cursor-pointer transition-all hover:shadow-lg hover:border-red-600"
      onMouseEnter={() => prefetchCategory(category.slug)}
      onFocus={() => prefetchCategory(category.slug)}
    >
      <div
        className={`w-12 h-12 md:w-16 md:h-16 rounded-full flex items-center justify-center mb-3 md:mb-4 overflow-hidden ${bgColors[index % bgColors.length]}`}
      >
        {category.image_thumb || category.image_hd || category.image ? (
          <img
            src={category.image_thumb || category.image_hd || category.image || undefined}
            alt={category.name}
            className="w-full h-full object-cover rounded-full"
            loading="lazy"
            decoding="async"
          />
        ) : (
          <Icon
            name={category.icon || icons[index % icons.length]}
            className={`size-[1em] text-2xl md:text-3xl ${textColors[index % textColors.length]}`}
          />
        )}
      </div>
      <h3 className="font-semibold text-base md:text-lg mb-1 md:mb-2">{category.name}</h3>
      <p className="text-gray-500 text-xs md:text-sm mb-2 md:mb-3">
        {category.products_count || 0}{' '}
        {category.products_count === 1
          ? 'товар'
          : category.products_count && category.products_count < 5
            ? 'товара'
            : 'товаров'}
      </p>
      {category.children && category.children.length > 0 && (
        <div className="flex flex-wrap gap-1">
          {category.children.slice(0, 2).map((sub) => (
            <span key={sub.id} className="text-xs text-gray-600 bg-gray-100 px-2 py-1 rounded">
              {sub.name}
            </span>
          ))}
        </div>
      )}
    </Link>
  );
}

const bgColors = [
  'bg-red-100',
  'bg-yellow-100',
  'bg-green-100',
  'bg-blue-100',
  'bg-purple-100',
  'bg-pink-100',
];
const textColors = [
  'text-red-600',
  'text-yellow-600',
  'text-green-600',
  'text-blue-600',
  'text-purple-600',
  'text-pink-600',
];
const icons = [
  Sofa,
  BedDouble,
  Utensils,
  Tv,
  DoorOpen,
  Baby,
  Briefcase,
  Archive,
  TableIcon,
  Armchair,
  Lightbulb,
  Palette,
  Layers,
];

const HOME_COLLECTION_FILTERS = new Set(['new', 'featured', 'sale']);

export default function Catalog() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const ssrData = useSSR();
  const initialCategories = ssrData?.home?.categories || [];

  useEffect(() => {
    const filter = searchParams.get('filter');
    if (filter && HOME_COLLECTION_FILTERS.has(filter)) {
      navigate(`/collections/${filter}`, { replace: true });
    }
  }, [searchParams, navigate]);

  const { data: categoriesData, isLoading } = useSWR(CATEGORIES_KEY, () => api.categories.list(), {
    fallbackData: initialCategories.length > 0 ? { data: initialCategories } : undefined,
    revalidateOnMount: initialCategories.length === 0,
    revalidateIfStale: true,
    revalidateOnFocus: false,
    dedupingInterval: 60_000,
    revalidateInterval: 300_000,
  });

  const categories = categoriesData?.data ?? [];
  const prefetchCategory = usePrefetchCategory();

  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-7xl mx-auto px-4 py-6 md:py-12">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-xs md:text-sm mb-4">
          <Link to="/" className="text-gray-600 hover:text-red-600">
            Главная
          </Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <span className="text-gray-900">Каталог</span>
        </div>

        <h1 className="text-2xl md:text-4xl font-bold mb-6 md:mb-8">Каталог мебели</h1>

        {isLoading ? (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            {[...Array(12)].map((_, i) => (
              <div
                key={i}
                className="bg-white border-2 border-gray-200 rounded-2xl p-4 md:p-6 animate-pulse"
              >
                <div className="w-12 h-12 md:w-16 md:h-16 rounded-full bg-gray-200 mb-3 md:mb-4" />
                <div className="h-5 bg-gray-200 rounded mb-2" />
                <div className="h-4 bg-gray-200 rounded w-20 mb-2" />
                <div className="flex gap-1">
                  <div className="h-4 bg-gray-200 rounded w-16" />
                  <div className="h-4 bg-gray-200 rounded w-16" />
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            {categories.map((category, index) => (
              <CategoryCard
                key={category.id}
                category={category}
                index={index}
                prefetchCategory={prefetchCategory}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
