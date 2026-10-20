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
  usePageSeo(product?.seo);

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
