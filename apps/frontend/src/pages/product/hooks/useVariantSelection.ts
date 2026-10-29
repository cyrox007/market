import { useNavigate, useSearchParams } from 'react-router-dom';
import type { ProductDetail, ProductVariant } from '../../../lib/api';
import {
  chooseValue,
  defaultVariant,
  isInStock,
  isValueAvailable,
  variantFromParam,
} from '../lib/variants';

export const VARIANT_PARAM = 'variant';

export interface VariantAttributeView {
  slug: string;
  name: string;
  isColor: boolean;
  selectedName: string | null;
  values: {
    slug: string;
    name: string;
    code: string | null;
    selected: boolean;
    available: boolean;
  }[];
}

/** Выбранный вариант живёт в адресе (?variant=id, замена истории) — так его видит и сервер */
export function useVariantSelection(product: ProductDetail) {
  const [params, setParams] = useSearchParams();
  const navigate = useNavigate();
  // Страницу открывают и на родителе, и на самом предложении: каталог отдаёт
  // отдельные позиции и ведёт прямо на них (market-docs/61)
  const variants = product.is_variable || product.is_variant ? (product.variants ?? []) : [];

  const selectedVariant: ProductVariant | null =
    variantFromParam(variants, params.get(VARIANT_PARAM)) ??
    (product.is_variant ? variants.find((v) => v.id === product.id) : undefined) ??
    defaultVariant(variants);

  const attributes: VariantAttributeView[] = selectedVariant
    ? (product.variation_attributes ?? [])
        .filter((a) => a.values?.length)
        .map((a) => {
          const current = selectedVariant.variation_attributes?.find(
            (x) => x.attribute_slug === a.attribute_slug,
          );
          const values = (a.values ?? [])
            .filter((v) => v.slug)
            .map((v) => ({
              slug: v.slug!,
              name: v.name ?? v.slug!,
              code: v.code ?? null,
              selected: v.slug === current?.value_slug,
              available: isValueAvailable(variants, selectedVariant, a.attribute_slug, v.slug!),
            }));
          return {
            slug: a.attribute_slug,
            name: a.attribute_name ?? a.attribute_slug,
            isColor: a.type === 'color',
            selectedName: values.find((v) => v.selected)?.name ?? current?.value_name ?? null,
            values,
          };
        })
    : [];

  const selectValue = (attributeSlug: string, valueSlug: string) => {
    if (!selectedVariant) return;
    const next = chooseValue(variants, selectedVariant, attributeSlug, valueSlug);
    if (next.id === selectedVariant.id) return;

    // У каждого предложения своя страница: со страницы предложения уходим на соседнюю,
    // иначе чужой товар показался бы под этим адресом. С родителя — как было, параметром.
    if (product.is_variant && next.slug) {
      navigate(`/product/${next.slug}`);
      return;
    }

    setParams(
      (prev) => {
        const nextParams = new URLSearchParams(prev);
        nextParams.set(VARIANT_PARAM, String(next.id));
        return nextParams;
      },
      { replace: true, preventScrollReset: true },
    );
  };

  /** В корзину — атрибуты выбранного варианта; у простого товара и у открытого варианта — без них */
  const cartAttributes = (): { attribute_slug: string; value_slug: string }[] | undefined => {
    if (!product.is_variable || product.is_variant) return undefined;
    if (!selectedVariant?.variation_attributes?.length) return undefined;
    return selectedVariant.variation_attributes.map((a) => ({
      attribute_slug: a.attribute_slug,
      value_slug: a.value_slug,
    }));
  };

  const isAvailable = selectedVariant ? isInStock(selectedVariant) : product.in_stock === true;

  return {
    selectedVariant,
    /** Для совместимости с блоком покупки до задачи 04: тот же выбранный вариант */
    matchedVariant: selectedVariant,
    attributes,
    selectValue,
    cartAttributes,
    isAvailable,
  };
}
