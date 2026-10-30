import type { ProductDetail, ProductVariant } from '../../../lib/api';
import { canOrder } from '../../../lib/availability';

/** Выбор варианта без React (market-docs/57): сочетание — вариант, которое есть у товара */

export const isInStock = (v: ProductVariant) => v.in_stock === true;

/** Группа вариантов: своего SKU для покупки нет, нужен выбранный вариант */
export const isVariableParent = (product: ProductDetail) =>
  Boolean(product.is_variable && !product.is_variant);

/**
 * Предложение комплекта без настроенного состава приходит с ценой 0 — продать его нельзя.
 * Флаг смотрим только у комплекта: обычным вариантам бэк тоже шлёт `is_bundle_configured: false`.
 */
export const isSellableOffer = (v: ProductVariant, isKit: boolean) =>
  !isKit || v.is_bundle_configured !== false;

const valueOf = (v: ProductVariant, attributeSlug: string) =>
  v.variation_attributes?.find((a) => a.attribute_slug === attributeSlug)?.value_slug;

/** Первый в наличии, иначе под заказ, иначе первый, который можно продать, иначе первый */
export function defaultVariant(variants: ProductVariant[], isKit = false): ProductVariant | null {
  return (
    variants.find(isInStock) ??
    variants.find((v) => canOrder(v) && isSellableOffer(v, isKit)) ??
    variants.find((v) => isSellableOffer(v, isKit)) ??
    variants[0] ??
    null
  );
}

export function variantFromParam(
  variants: ProductVariant[],
  param: string | null,
): ProductVariant | null {
  if (!param || !/^\d+$/.test(param)) return null;
  return variants.find((v) => v.id === Number(param)) ?? null;
}

/** Совпадающих с текущим вариантом значений, кроме меняемого атрибута */
const sameOthers = (v: ProductVariant, current: ProductVariant, attributeSlug: string) =>
  (current.variation_attributes ?? []).filter(
    (a) => a.attribute_slug !== attributeSlug && valueOf(v, a.attribute_slug) === a.value_slug,
  ).length;

const othersCount = (current: ProductVariant, attributeSlug: string) =>
  (current.variation_attributes ?? []).filter((a) => a.attribute_slug !== attributeSlug).length;

/** Есть продаваемый вариант с этим значением и остальными значениями текущего */
export function isValueAvailable(
  variants: ProductVariant[],
  current: ProductVariant,
  attributeSlug: string,
  valueSlug: string,
  isKit = false,
): boolean {
  const need = othersCount(current, attributeSlug);
  return variants.some(
    (v) =>
      isSellableOffer(v, isKit) &&
      valueOf(v, attributeSlug) === valueSlug &&
      sameOthers(v, current, attributeSlug) === need,
  );
}

/** Вариант после выбора значения: точное сочетание, иначе ближайший (больше совпадений, в наличии, под заказ) */
export function chooseValue(
  variants: ProductVariant[],
  current: ProductVariant,
  attributeSlug: string,
  valueSlug: string,
  isKit = false,
): ProductVariant {
  let best: ProductVariant | null = null;
  let bestScore = -1;
  for (const v of variants) {
    if (valueOf(v, attributeSlug) !== valueSlug) continue;
    // Совпадения важнее наличия: в наличии, затем под заказ — только при равенстве.
    // Непродаваемое предложение комплекта — только если продаваемого с этим значением нет
    const score =
      (isSellableOffer(v, isKit) ? 1000 : 0) +
      sameOthers(v, current, attributeSlug) * 4 +
      (isInStock(v) ? 2 : 0) +
      (canOrder(v) ? 1 : 0);
    if (score > bestScore) {
      best = v;
      bestScore = score;
    }
  }
  return best ?? current;
}
