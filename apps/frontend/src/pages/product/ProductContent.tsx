import { useState } from 'react';
import { prefersReducedMotion } from '../../lib/motion';
import type { Product, ProductDetail } from '../../lib/api';
import { useRegion } from '../../hooks/useRegion';
import BuyBlock from './components/BuyBlock';
import DeliveryInfo from './components/DeliveryInfo';
import Gallery from './components/Gallery';
import PhotoViewer from './components/PhotoViewer';
import ProductBreadcrumbs from './components/ProductBreadcrumbs';
import { ReviewsEmpty } from './components/ProductEmptyState';
import ProductTabs, { PRODUCT_DETAILS_ID, type ProductTabId } from './components/ProductTabs';
import RelatedProducts from './components/RelatedProducts';
import WishlistCompareButtons from './components/WishlistCompareButtons';
import { useDeliveryQuote } from './hooks/useDeliveryQuote';
import { useProductCartActions } from './hooks/useProductCartActions';
import { useVariantSelection } from './hooks/useVariantSelection';
import { getSpecificationsArray, hasMeaningfulProductText } from './lib/productText';

interface Props {
  product: ProductDetail;
  related: Product[];
  isRefreshing: boolean;
}

/** Страница одного товара; на другой товар — новый экземпляр (key), состояние не переносится */
export default function ProductContent({ product, related, isRefreshing }: Props) {
  const variants = useVariantSelection(product);
  const { selectedVariant } = variants;
  const cart = useProductCartActions({
    product,
    selectedVariant,
    cartAttributes: variants.cartAttributes,
  });
  const { locality, region } = useRegion();
  const city = locality?.name ?? region?.name ?? null;
  const delivery = useDeliveryQuote(product, selectedVariant, cart.quantity);

  const [tab, setTab] = useState<ProductTabId>('description');

  // Фото варианта, если они есть; иначе — товара
  const images = selectedVariant?.images?.length
    ? selectedVariant.images
    : product.images?.length
      ? product.images
      : product.image_hd
        ? [product.image_hd]
        : product.image
          ? [product.image]
          : [];
  const [image, setImage] = useState(0);
  const [viewerOpen, setViewerOpen] = useState(false);
  const [prevVariantId, setPrevVariantId] = useState(selectedVariant?.id);
  if (prevVariantId !== selectedVariant?.id) {
    setPrevVariantId(selectedVariant?.id);
    setImage(0);
  }

  const description =
    selectedVariant && hasMeaningfulProductText(selectedVariant.description)
      ? selectedVariant.description
      : product.description;
  const variantSpecs = selectedVariant
    ? getSpecificationsArray(selectedVariant.specifications)
    : [];
  const specifications =
    variantSpecs.length > 0
      ? variantSpecs
      : getSpecificationsArray(
          product.specifications,
          (product as { specification_names?: Record<string, string> }).specification_names,
        );

  const goToReviews = () => {
    setTab('reviews');
    document
      .getElementById(PRODUCT_DETAILS_ID)
      ?.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
  };

  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-[1280px] mx-auto px-4 py-6">
        <ProductBreadcrumbs product={product} />

        <div className="mb-12 grid grid-cols-1 gap-6 md:grid-cols-[minmax(0,1fr)_441.5px]">
          <div className="min-w-0">
            <Gallery
              images={images}
              productName={product.name ?? 'Товар'}
              selectedIndex={image}
              onSelectIndex={setImage}
              onOpen={(i) => {
                setImage(i);
                setViewerOpen(true);
              }}
            />
            <PhotoViewer
              open={viewerOpen}
              images={images}
              productName={product.name ?? 'Товар'}
              index={image}
              onIndexChange={setImage}
              onClose={() => setViewerOpen(false)}
            />
          </div>

          <BuyBlock
            product={product}
            selectedVariant={selectedVariant}
            attributes={variants.attributes}
            onSelectValue={variants.selectValue}
            disabled={isRefreshing}
            city={city}
            cart={cart}
            onGoToReviews={goToReviews}
            onOneClick={() => {}}
            delivery={<DeliveryInfo city={city} {...delivery} />}
            titleActions={
              <WishlistCompareButtons product={product} matchedVariant={selectedVariant} />
            }
          />
        </div>

        <ProductTabs
          activeTab={tab}
          onTabChange={setTab}
          description={description}
          specifications={specifications}
          reviewsCount={product.reviews_count ?? 0}
          // Список и сводка — задача 10
          // Окно отзыва — задача 11
          reviews={!product.reviews_count && <ReviewsEmpty onWriteReview={() => {}} />}
        />

        <div className="space-y-16">
          <RelatedProducts products={related} />
        </div>
      </div>
    </div>
  );
}
