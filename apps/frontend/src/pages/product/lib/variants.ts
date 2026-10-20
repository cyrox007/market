import type { ProductVariant } from '../../../lib/api';

/** Выбор варианта без React (market-docs/57): сочетание — вариант, которое есть у товара */

export const isInStock = (v: ProductVariant) => v.in_stock === true;

const valueOf = (v: ProductVariant, attributeSlug: string) =>
  v.variation_attributes?.find((a) => a.attribute_slug === attributeSlug)?.value_slug;

/** Первый в наличии, иначе первый */
export function defaultVariant(variants: ProductVariant[]): ProductVariant | null {
  return variants.find(isInStock) ?? variants[0] ?? null;
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

/** Есть вариант с этим значением и остальными значениями текущего */
export function isValueAvailable(
  variants: ProductVariant[],
  current: ProductVariant,
  attributeSlug: string,
  valueSlug: string,
): boolean {
  const need = othersCount(current, attributeSlug);
  return variants.some(
    (v) =>
      valueOf(v, attributeSlug) === valueSlug && sameOthers(v, current, attributeSlug) === need,
  );
}

/** Вариант после выбора значения: точное сочетание, иначе ближайший (больше совпадений, в наличии) */
export function chooseValue(
  variants: ProductVariant[],
  current: ProductVariant,
  attributeSlug: string,
  valueSlug: string,
): ProductVariant {
  let best: ProductVariant | null = null;
  let bestScore = -1;
  for (const v of variants) {
    if (valueOf(v, attributeSlug) !== valueSlug) continue;
    // Совпадения важнее наличия: в наличии — только при равенстве
    const score = sameOthers(v, current, attributeSlug) * 2 + (isInStock(v) ? 1 : 0);
    if (score > bestScore) {
      best = v;
      bestScore = score;
    }
  }
  return best ?? current;
}
