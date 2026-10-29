import type { BundleComponent, ProductDetail, ProductVariant } from '../../../lib/api';
import { isVariableParent } from './variants';

/** Продаваемый комплект (market-docs/66): заказ собирается из реальных SKU состава */

export interface BundlePart {
  key: string;
  /** Реальный SKU части; без него часть не передать в расчёт доставки */
  productId: number | null;
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
  if (isVariableParent(product) && !selectedVariant) return null;
  return { bundleId, primarySkuId: selectedVariant?.id ?? product.id };
}

const toPart = (c: BundleComponent): BundlePart => ({
  key: String(c.product_id ?? c.id ?? c.sku),
  productId: c.product_id ?? c.id ?? null,
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
  // Простой комплект: основное изделие — тоже часть заказа; новый бэк кладёт его в состав сам
  if (own.some((c) => (c.product_id ?? c.id) === product.id)) return own.map(toPart);
  return [
    {
      key: String(product.id),
      productId: product.id,
      name: product.name,
      sku: product.sku,
      quantity: 1,
      total: null,
    },
    ...own.map(toPart),
  ];
}

/**
 * Расчёт доставки комплекта — по его реальным частям: бэк сверяет `items` с составом
 * выбранного предложения (bundle-multi-warehouse-stock.md, «API: доставка»).
 */
export function bundleQuoteRequest(
  product: ProductDetail,
  selectedVariant: ProductVariant | null,
  quantity: number,
) {
  const checkout = bundleCheckout(product, selectedVariant);
  const parts = bundleParts(product, selectedVariant);
  if (!checkout || !parts.length || parts.some((p) => p.productId === null)) return null;
  return {
    bundle_id: checkout.bundleId,
    primary_sku_id: checkout.primarySkuId,
    bundle_quantity: quantity,
    items: parts.map((p) => ({ product_id: p.productId!, quantity: p.quantity * quantity })),
  };
}
