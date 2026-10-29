import { useParams } from 'react-router-dom';
import { usePageSeo } from '../../hooks/usePageSeo';
import ProductContent from './ProductContent';
import {
  ProductLoadError,
  ProductNotFound,
  ProductPageSkeleton,
} from './components/ProductPageStates';
import { useProductPage } from './hooks/useProductPage';

export default function ProductPage() {
  const { slug } = useParams<{ slug: string }>();
  const { product, status, related, isRefreshing, retry } = useProductPage(slug);
  // canonical без ?variant, если бэк его не прислал; эффект — только в браузере
  const seo = product?.seo;
  usePageSeo(
    seo && {
      ...seo,
      canonical_url:
        seo.canonical_url ??
        (typeof window !== 'undefined'
          ? `${window.location.origin}/product/${product.slug}`
          : null),
    },
  );

  if (status === 'not-found') return <ProductNotFound />;
  if (status === 'error') return <ProductLoadError onRetry={retry} />;
  if (!product) return <ProductPageSkeleton />;

  return (
    <ProductContent
      key={product.slug}
      product={product}
      related={related}
      isRefreshing={isRefreshing}
    />
  );
}
