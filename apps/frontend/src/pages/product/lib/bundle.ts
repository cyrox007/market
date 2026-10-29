import type { BundleComponent, ProductDetail, ProductVariant } from '../../../lib/api';

/** Продаваемый комплект (market-docs/66): заказ собирается из реальных SKU состава */

export interface BundlePart {
  key: string;
  name: string;
  sku: string;
  quantity: number;
  /** Сумма за часть; у простого комплекта бэк цены частей не отдаёт */
  total: number | null;
}

/** Параметры прямого оформления: бэк ищет `bundle_id` только среди корней */
export function bundleCheckout(
  product: ProductDetail,
  selectedVariant: ProductVariant | null,
): { bundleId: number; primarySkuId: number } | null {
  if (!product.is_sellable_bundle) return null;
  const bundleId = product.is_variant && product.parent_id ? product.parent_id : product.id;
  if (product.is_variable && !product.is_variant && !selectedVariant) return null;
  return { bundleId, primarySkuId: selectedVariant?.id ?? product.id };
}

const toPart = (c: BundleComponent): BundlePart => ({
  key: String(c.product_id ?? c.id ?? c.sku),
  name: c.name,
  sku: c.sku,
  quantity: c.quantity,
  total: typeof c.total === 'number' ? c.total : null,
});

export function bundleParts(
  product: ProductDetail,
  selectedVariant: ProductVariant | null,
): BundlePart[] {
  if (!product.is_sellable_bundle) return [];
  const own = product.sellable_bundle_components ?? [];
  if (product.is_variable || product.is_variant) {
    if (selectedVariant?.bundle_components?.length)
      return selectedVariant.bundle_components.map(toPart);
    // Состав в товаре — у открытого предложения; у соседнего он свой
    return !selectedVariant || selectedVariant.id === product.id ? own.map(toPart) : [];
  }
  // Простой комплект: основное изделие — тоже часть заказа
  return [
    { key: String(product.id), name: product.name, sku: product.sku, quantity: 1, total: null },
    ...own.map(toPart),
  ];
}
