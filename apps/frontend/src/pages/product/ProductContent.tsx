import { useState } from 'react';
import ProductGallery from '../../components/product/ProductGallery';
import type { Product, ProductDetail } from '../../lib/api';
import BuyPanel from './components/BuyPanel';
import ProductBreadcrumbs from './components/ProductBreadcrumbs';
import ProductSummary from './components/ProductSummary';
import ProductTabs, { PRODUCT_DETAILS_ID, type ProductTabId } from './components/ProductTabs';
import RelatedProducts from './components/RelatedProducts';
import WishlistCompareButtons from './components/WishlistCompareButtons';
import { useProductCartActions } from './hooks/useProductCartActions';
import { useVariantSelection } from './hooks/useVariantSelection';
import {
  getSpecificationsArray,
  hasMeaningfulProductText,
  productHasAnyDescription,
} from './lib/productText';

interface Props {
  product: ProductDetail;
  related: Product[];
  isRefreshing: boolean;
}

/** Страница одного товара; на другой товар — новый экземпляр (key), состояние не переносится */
export default function ProductContent({ product, related, isRefreshing }: Props) {
  const variants = useVariantSelection(product);
  const { selectedVariant, matchedVariant } = variants;
  const cart = useProductCartActions({
    product,
    matchedVariant,
    selectedVariant,
    cartAttributes: variants.cartAttributes,
  });

  const hasDescription = productHasAnyDescription(product);
  const [tab, setTab] = useState<ProductTabId>(hasDescription ? 'description' : 'specs');
  const activeTab = !hasDescription && tab === 'description' ? 'specs' : tab;

  // Фото варианта, если они есть; иначе — товара
  const images = matchedVariant?.images?.length
    ? matchedVariant.images
    : product.images?.length
      ? product.images
      : product.image_hd
        ? [product.image_hd]
        : product.image
          ? [product.image]
          : [];
  const [image, setImage] = useState(0);
  const [prevSelection, setPrevSelection] = useState(variants.selection);
  if (prevSelection !== variants.selection) {
    setPrevSelection(variants.selection);
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

  const goToDetails = () => {
    setTab(hasDescription ? 'description' : 'specs');
    document
      .getElementById(PRODUCT_DETAILS_ID)
      ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-[1280px] mx-auto px-4 py-6">
        <ProductBreadcrumbs product={product} />

        <div className="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6 mb-12">
          <div className="flex flex-col md:flex-row gap-4">
            <div className="flex flex-col flex-1 w-full md:max-w-[500px] order-1 md:order-2 min-w-0">
              <ProductGallery
                images={images}
                productName={product.name ?? 'Товар'}
                selectedIndex={image}
                onSelectIndex={setImage}
              />
              <WishlistCompareButtons product={product} matchedVariant={matchedVariant} />
            </div>

            <ProductSummary
              product={product}
              selectedVariant={selectedVariant}
              selection={variants.selection}
              isValueAvailable={variants.isValueAvailable}
              onSelectValue={variants.selectValue}
              onPickVariant={variants.pickVariant}
              disabled={isRefreshing}
              hasDescription={hasDescription}
              onGoToDetails={goToDetails}
            />
          </div>

          <BuyPanel
            product={product}
            selectedVariant={selectedVariant}
            sku={matchedVariant?.sku || product.sku}
            isAvailable={variants.isAvailable}
            cart={cart}
          />
        </div>

        <ProductTabs
          activeTab={activeTab}
          onTabChange={setTab}
          showDescription={hasDescription}
          description={description}
          specifications={specifications}
        />

        <div className="space-y-16">
          <RelatedProducts products={related} />
        </div>
      </div>
    </div>
  );
}
