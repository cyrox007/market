import { useEffect, useState } from 'react';
import type { ProductDetail, ProductVariant, SelectedVariationItem } from '../../../lib/api';

type Selection = Record<string, string>;
type VariantLike = { variation_attributes?: SelectedVariationItem[] };

const isInStock = (v: ProductVariant) => v.in_stock === true && (v.stock ?? 0) > 0;

const toSelection = (items: SelectedVariationItem[] | undefined): Selection =>
  (items ?? []).reduce((acc: Selection, a) => {
    acc[a.attribute_slug] = a.value_slug;
    return acc;
  }, {});

/** У варианта может быть меньше атрибутов, чем у товара: сверяем только его атрибуты */
function matchesSelection(v: VariantLike, selection: Selection): boolean {
  const va = v.variation_attributes ?? [];
  if (va.length === 0) return Object.keys(selection).length === 0;
  return va.every((a) => {
    const selected = selection[a.attribute_slug];
    return selected !== undefined && selected !== '' && a.value_slug === selected;
  });
}

/** Выбор не противоречит атрибутам варианта (не выбрано или совпадает) */
function compatibleWith(v: VariantLike, selection: Selection): boolean {
  const va = v.variation_attributes ?? [];
  if (va.length === 0) return Object.keys(selection).length === 0;
  return va.every((a) => {
    const selected = selection[a.attribute_slug];
    return selected === undefined || selected === '' || selected === a.value_slug;
  });
}

/** Из подходящих — вариант с наибольшим числом атрибутов: так видны все блоки (материал, размер…) */
const richest = <T extends VariantLike>(variants: T[]) =>
  variants.reduce((best, v) =>
    (v.variation_attributes?.length ?? 0) > (best.variation_attributes?.length ?? 0) ? v : best,
  );

const mergeIfChanged = (prev: Selection, from: Selection) =>
  Object.entries(from).some(([k, val]) => prev[k] !== val) ? { ...prev, ...from } : prev;

/** Выбор варианта на странице товара (перенесено из page.tsx без изменений, задача 03 — переписать) */
export function useVariantSelection(product: ProductDetail) {
  const [selection, setSelection] = useState<Selection>(() =>
    toSelection(product.selected_variation),
  );
  const [pickedVariant, setPickedVariant] = useState<ProductVariant | null>(null);

  // Новый ответ API (например, после смены региона) — выбор с бэка
  const [prevProduct, setPrevProduct] = useState(product);
  if (prevProduct !== product) {
    setPrevProduct(product);
    if (product.selected_variation?.length) setSelection(toSelection(product.selected_variation));
  }

  const isVariableParent = Boolean(
    product.is_variable && !product.is_variant && product.variants?.length,
  );

  // Дополняем выбор атрибутами подходящего варианта в наличии
  useEffect(() => {
    if (!isVariableParent) return;
    const inStock = (product.variants ?? []).filter(isInStock);
    if (inStock.length === 0) return;
    const matching = inStock.filter((v) => compatibleWith(v, selection));
    const exact = matching.find((v) => matchesSelection(v, selection));
    if (exact?.variation_attributes?.length) {
      setSelection((prev) => mergeIfChanged(prev, toSelection(exact.variation_attributes)));
      return;
    }
    if (matching.length > 0) {
      const variant = richest(matching);
      if (variant.variation_attributes?.length) {
        setSelection((prev) => mergeIfChanged(prev, toSelection(variant.variation_attributes)));
      }
    }
  }, [isVariableParent, product.variants, selection]);

  const findSelectedVariant = (): ProductVariant | null => {
    if (!isVariableParent) return null;
    const inStock = product.variants!.filter(isInStock);
    if (inStock.length === 0) return null;
    const matching = inStock.filter((v) => matchesSelection(v, selection));
    if (matching.length === 0) {
      return Object.keys(selection).length === 0 && inStock.length === 1 ? inStock[0] : null;
    }
    return richest(matching);
  };

  const matchedVariant = findSelectedVariant();
  const selectedVariant =
    pickedVariant ??
    matchedVariant ??
    (product.is_variant && product.variants?.length
      ? (product.variants.find((v) => v.id === product.id) ?? null)
      : null);

  /** Значение доступно при текущем выборе остальных атрибутов */
  const isValueAvailable = (attributeSlug: string, valueSlug: string): boolean => {
    if (!product.is_variable || !product.variants?.length) return true;
    const temp = { ...selection, [attributeSlug]: valueSlug };
    return product.variants.some((v) => {
      if (!(v.in_stock ?? true) || (v.stock ?? 0) <= 0) return false;
      const va = v.variation_attributes ?? [];
      if (va.length === 0) return Object.keys(temp).length === 0;
      const hasThis = va.some(
        (a) => a.attribute_slug === attributeSlug && a.value_slug === valueSlug,
      );
      return hasThis && compatibleWith(v, temp);
    });
  };

  /** Клик по недоступному значению — первый вариант в наличии с этим значением */
  const selectValue = (attributeSlug: string, valueSlug: string, isDisabledClick = false) => {
    if (!valueSlug || !product.variants?.length) return;
    const withValue = (v: ProductVariant) =>
      (v.in_stock ?? true) &&
      (v.stock ?? 0) > 0 &&
      v.variation_attributes?.some(
        (a) => a.attribute_slug === attributeSlug && a.value_slug === valueSlug,
      );

    if (isDisabledClick) {
      const first = product.variants.find(withValue);
      if (first?.variation_attributes?.length)
        setSelection(toSelection(first.variation_attributes));
      return;
    }

    setSelection((prev) => {
      const attrSlugs = product.variation_attributes?.map((a) => a.attribute_slug) ?? [];
      if (!attrSlugs.length) return { ...prev, [attributeSlug]: valueSlug };
      const candidates = (product.variants ?? []).filter(withValue);
      if (candidates.length === 0) return prev;
      const next: Selection = { ...prev, [attributeSlug]: valueSlug };
      for (const slug of attrSlugs) {
        if (slug === attributeSlug) continue;
        const current = next[slug];
        const available =
          !current ||
          candidates.some((v) =>
            v.variation_attributes?.some(
              (a) => a.attribute_slug === slug && a.value_slug === current,
            ),
          );
        if (!available) {
          const first = candidates[0].variation_attributes?.find((a) => a.attribute_slug === slug);
          if (first) next[slug] = first.value_slug;
        }
      }
      return next;
    });
  };

  const pickVariant = (variantId: number) => {
    const variant = product.variants?.find((v) => v.id === variantId);
    if (!variant) return;
    setPickedVariant(variant);
    setSelection(toSelection(variant.variation_attributes));
  };

  /** В корзину — атрибуты варианта, который реально выбран */
  const cartAttributes = (): { attribute_slug: string; value_slug: string }[] | undefined => {
    if (!product.is_variable || product.is_variant) return undefined;
    const variant =
      matchedVariant ??
      (() => {
        const matching = (product.variants ?? [])
          .filter(isInStock)
          .filter((v) => compatibleWith(v, selection));
        return matching.length > 0 ? richest(matching) : null;
      })();
    if (!variant?.variation_attributes?.length) return undefined;
    return variant.variation_attributes.map((a) => ({
      attribute_slug: a.attribute_slug,
      value_slug: a.value_slug,
    }));
  };

  /** Можно ли купить при текущем выборе (у вариативного — есть ли подходящий вариант в наличии) */
  const isAvailable = (() => {
    if (!(product.is_variable && !product.is_variant && product.variants)) {
      return product.in_stock === true;
    }
    if (matchedVariant) return isInStock(matchedVariant);
    return product.variants.filter(isInStock).some((v) => compatibleWith(v, selection));
  })();

  return {
    selection,
    selectedVariant,
    matchedVariant,
    isValueAvailable,
    selectValue,
    pickVariant,
    cartAttributes,
    isAvailable,
  };
}
