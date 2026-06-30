import { useState, useEffect } from 'react';
import { useProductVariations } from '../../hooks/useProductVariations';
import VariantAttributeSelector from '../product/VariantAttributeSelector';
import type { CartItem, SelectedVariationItem } from '../../lib/api';
import type { VariationAttributeOption } from '../../lib/api';
import { getAttributeLabel, getValueLabel } from '../../utils/variationDisplay';

export type VariationAttributesPayload = { attribute_slug: string; value_slug: string }[];

interface CartItemVariantsProps {
  item: CartItem;
  regionId?: number;
  isUpdating: boolean;
  onVariantChange: (itemId: number, quantity: number, variationAttributes?: VariationAttributesPayload) => Promise<void>;
}

/** Текущая выбранная комбинация: из корзины или из pending (при открытой форме) */
function getCurrentSelection(
  item: CartItem,
  pendingVariation: Record<string, string> | null
): Record<string, string> {
  if (pendingVariation && Object.keys(pendingVariation).length > 0) {
    return pendingVariation;
  }
  const fromItem = (item.variation_attributes ?? []).reduce<Record<string, string>>((acc, a) => {
    acc[a.attribute_slug] = a.value_slug;
    return acc;
  }, {});
  return fromItem;
}

export default function CartItemVariants({
  item,
  regionId,
  isUpdating,
  onVariantChange,
}: CartItemVariantsProps) {
  const [isOpen, setIsOpen] = useState(false);
  /** При открытой форме — выбранные значения по attribute_slug; при закрытой не используется */
  const [pendingVariation, setPendingVariation] = useState<Record<string, string> | null>(null);
  const [applyError, setApplyError] = useState<string | null>(null);
  const { variations, loading, loadVariations } = useProductVariations();

  const variationData = variations[item.id];
  const isLoading = loading[item.id];
  const selection = getCurrentSelection(item, isOpen ? pendingVariation : null);
  const hasVariationAttributes = (item.variation_attributes?.length ?? 0) > 0;

  // Названия свойств (Цвет, Размер…) для подписи в корзине
  useEffect(() => {
    if (!item.slug || !hasVariationAttributes) return;
    if (variationData || isLoading) return;
    void loadVariations(item.slug, item.id, regionId).catch(() => {});
  }, [item.id, item.slug, regionId, hasVariationAttributes, variationData, isLoading, loadVariations]);

  const handleOpen = async () => {
    setIsOpen(true);
    setPendingVariation(null);
    setApplyError(null);
    if (!variationData && !isLoading && item.slug) {
      try {
        await loadVariations(item.slug, item.id, regionId);
      } catch (err) {
        console.error('Failed to load variations:', err);
      }
    }
  };

  const handleClose = () => {
    setIsOpen(false);
    setPendingVariation(null);
    setApplyError(null);
  };

  const attrs = variationData?.product?.variation_attributes ?? [];
  const variants = variationData?.product?.variants ?? [];

  // Как на карточке товара: среди совместимых с выбором берём вариацию с макс. числом атрибутов
  const selectionKey = Object.entries(selection)
    .filter(([, v]) => v)
    .map(([a, b]) => `${a}:${b}`)
    .sort()
    .join(',');
  useEffect(() => {
    if (!isOpen || !variants.length) return;
    
    // Фильтруем только вариации в наличии
    const inStock = (v: { in_stock?: boolean; stock?: number }) => (v.in_stock ?? true) && (v.stock ?? 0) > 0;
    const inStockVariants = variants.filter(inStock);
    if (inStockVariants.length === 0) return;
    
    const compatible = (v: { variation_attributes?: SelectedVariationItem[]; in_stock?: boolean; stock?: number }) => {
      const va = v.variation_attributes ?? [];
      if (va.length === 0) return Object.keys(selection).length === 0;
      for (const a of va) {
        const sel = selection[a.attribute_slug];
        if (sel !== undefined && sel !== '' && sel !== a.value_slug) return false;
      }
      return true;
    };
    
    const matching = inStockVariants.filter(compatible);
    const variant = matching.length === 0 ? null : matching.reduce((best, v) =>
      (v.variation_attributes?.length ?? 0) > (best.variation_attributes?.length ?? 0) ? v : best
    );
    
    if (!variant?.variation_attributes?.length) return;
    
    const fromVariant = variant.variation_attributes.reduce((acc: Record<string, string>, a: SelectedVariationItem) => {
      acc[a.attribute_slug] = a.value_slug;
      return acc;
    }, {});
    
    setPendingVariation((prev) => {
      const current = prev ?? selection;
      let same = true;
      for (const [k, val] of Object.entries(fromVariant)) {
        if (current[k] !== val) {
          same = false;
          break;
        }
      }
      if (same) return prev;
      return { ...current, ...fromVariant };
    });
  }, [isOpen, variants.length, selectionKey]);

  /** Вариация подходит под выбор: все её атрибуты совпадают с sel. availableOnly = только в наличии. */
  const variantMatches = (
    sel: Record<string, string>,
    v: { variation_attributes?: SelectedVariationItem[]; in_stock?: boolean; stock?: number },
    availableOnly = true
  ): boolean => {
    const va = v.variation_attributes ?? [];
    if (va.length === 0) return Object.keys(sel).length === 0;
    for (const a of va) {
      const selectedVal = sel[a.attribute_slug];
      if (selectedVal === undefined || selectedVal === '' || a.value_slug !== selectedVal) return false;
    }
    if (availableOnly && !((v.in_stock ?? true) && (v.stock ?? 0) > 0)) return false;
    return true;
  };

  /** Доступно ли значение (есть ли вариация в наличии с этой комбинацией) */
  const isValueAvailableForAttribute = (attributeSlug: string, valueSlug: string): boolean => {
    if (!variants.length) return true;
    
    // Создаем временный выбор с новым значением
    const tempSelection = { ...selection, [attributeSlug]: valueSlug };
    
    return variants.some((v: { variation_attributes?: SelectedVariationItem[]; in_stock?: boolean; stock?: number }) => {
      // Проверяем наличие в наличии
      if (!(v.in_stock ?? true) || (v.stock ?? 0) <= 0) return false;
      
      const va = v.variation_attributes ?? [];
      if (va.length === 0) return Object.keys(tempSelection).length === 0;
      
      // Проверяем, что вариация имеет это значение
      const hasThis = va.some((a: SelectedVariationItem) => a.attribute_slug === attributeSlug && a.value_slug === valueSlug);
      if (!hasThis) return false;
      
      // Проверяем совместимость с остальными выбранными атрибутами
      // Вариация совместима, если все её атрибуты не противоречат выбору
      for (const a of va) {
        const selectedVal = tempSelection[a.attribute_slug];
        if (selectedVal !== undefined && selectedVal !== '' && a.value_slug !== selectedVal) {
          return false;
        }
      }
      
      return true;
    });
  };

  const handleSelectAttribute = (attributeSlug: string, valueSlug: string, isDisabledClick = false) => {
    if (isUpdating || isLoading) return;

    if (isDisabledClick) {
      const firstAvailable = variants.find(
        (v: { variation_attributes?: SelectedVariationItem[]; in_stock?: boolean; stock?: number }) =>
          (v.in_stock ?? true) &&
          (v.stock ?? 0) > 0 &&
          v.variation_attributes?.some((a: SelectedVariationItem) => a.attribute_slug === attributeSlug && a.value_slug === valueSlug)
      );
      if (firstAvailable?.variation_attributes?.length) {
        const next = firstAvailable.variation_attributes.reduce((acc: Record<string, string>, a: SelectedVariationItem) => {
          acc[a.attribute_slug] = a.value_slug;
          return acc;
        }, {});
        setPendingVariation(next);
      }
      return;
    }

    setPendingVariation((prev) => {
      const attrSlugs = attrs.map((a: VariationAttributeOption) => a.attribute_slug);

      const variantsWithThis = variants.filter(
        (v: { variation_attributes?: SelectedVariationItem[]; in_stock?: boolean; stock?: number }) =>
          (v.in_stock ?? true) &&
          (v.stock ?? 0) > 0 &&
          v.variation_attributes?.some((a: SelectedVariationItem) => a.attribute_slug === attributeSlug && a.value_slug === valueSlug)
      );

      if (variantsWithThis.length === 0) return prev ?? selection;

      const next = { ...(prev ?? selection), [attributeSlug]: valueSlug };
      for (const slug of attrSlugs) {
        if (slug === attributeSlug) continue;
        const current = next[slug];
        const available = !current || variantsWithThis.some((v: { variation_attributes?: SelectedVariationItem[] }) =>
          v.variation_attributes?.some((a: SelectedVariationItem) => a.attribute_slug === slug && a.value_slug === current)
        );
        if (!available) {
          const first = variantsWithThis[0].variation_attributes?.find((a: SelectedVariationItem) => a.attribute_slug === slug);
          if (first) next[slug] = first.value_slug;
        }
      }
      return next;
    });
  };

  const matchingVariants = variants.filter((v: { variation_attributes?: SelectedVariationItem[]; in_stock?: boolean; stock?: number }) =>
    variantMatches(selection, v, true)
  );
  const matchingVariantForSelection = matchingVariants.length === 0 ? undefined : matchingVariants.reduce((best, v) =>
    (v.variation_attributes?.length ?? 0) > (best.variation_attributes?.length ?? 0) ? v : best
  );

  const currentItemKey = (item.variation_attributes ?? [])
    .map((a: SelectedVariationItem) => `${a.attribute_slug}:${a.value_slug}`)
    .sort()
    .join(',');
  const selectionUnchanged = currentItemKey === selectionKey;
  const canApply = selectionUnchanged || !!matchingVariantForSelection;

  const handleApply = async () => {
    if (isUpdating || isLoading) return;
    setApplyError(null);

    if (selectionUnchanged) {
      handleClose();
      return;
    }

    const matchingVariant = matchingVariantForSelection;
    if (!matchingVariant?.variation_attributes?.length) {
      setApplyError('Выберите доступную комбинацию параметров');
      return;
    }

    const newAttrs: VariationAttributesPayload = matchingVariant.variation_attributes.map((a: SelectedVariationItem) => ({
      attribute_slug: a.attribute_slug,
      value_slug: a.value_slug,
    }));

    try {
      await onVariantChange(item.id, item.quantity, newAttrs);
      handleClose();
    } catch (err: any) {
      console.error('Failed to update variant:', err);
      const msg = err?.data?.message ?? (err?.status === 404 || String(err?.message || '').includes('404') ? 'Выбранная комбинация недоступна. Проверьте параметры.' : 'Не удалось изменить вариант');
      setApplyError(typeof msg === 'string' ? msg : 'Не удалось изменить вариант');
    }
  };

  if (!hasVariationAttributes && !item.is_variant) return null;

  const formatPrice = (price: number) => new Intl.NumberFormat('ru-RU').format(price) + ' ₽';

  const attributeNameBySlug = attrs.reduce<Record<string, string>>((acc, a) => {
    if (a.attribute_name?.trim()) {
      acc[a.attribute_slug] = a.attribute_name.trim();
    }
    return acc;
  }, {});

  return (
    <div className="mb-3 space-y-2">
      {!isOpen && (
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div className="text-sm text-gray-600 flex flex-col gap-0.5 sm:flex-row sm:flex-wrap sm:gap-x-4 sm:gap-y-1">
            {item.variation_attributes?.map((a: SelectedVariationItem) => (
              <p key={a.attribute_slug}>
                <span>{getAttributeLabel(a, attributeNameBySlug)}: </span>
                <span className="font-medium text-gray-900">{getValueLabel(a)}</span>
              </p>
            ))}
          </div>
          <button
            onClick={handleOpen}
            className="w-full sm:w-auto text-sm text-red-600 hover:text-red-700 font-medium flex items-center justify-center gap-1 py-2.5 px-3 rounded-lg border border-red-200 hover:border-red-400 sm:border-transparent sm:py-0 sm:px-0"
          >
            <i className="ri-edit-line"></i>
            Изменить вариант
          </button>
        </div>
      )}

      {isOpen && (
        <div className="border-t border-gray-200 pt-3 mt-3">
          {isLoading ? (
            <div className="text-sm text-gray-500">Загрузка вариантов...</div>
          ) : variationData ? (
            <div className="space-y-3">
              <div className="flex items-center justify-between gap-3 mb-2 flex-wrap">
                <span className="text-sm font-medium text-gray-700">Выберите вариант:</span>
                <div className="flex items-center gap-2">
                  {matchingVariantForSelection != null && (
                    <div className="text-sm font-semibold text-red-600">
                      {formatPrice(matchingVariantForSelection.price)}
                      {matchingVariantForSelection.old_price != null && matchingVariantForSelection.old_price > matchingVariantForSelection.price && (
                        <span className="ml-2 text-gray-400 font-normal line-through">{formatPrice(matchingVariantForSelection.old_price)}</span>
                      )}
                    </div>
                  )}
                  <button onClick={handleClose} className="text-sm text-gray-600 hover:text-gray-800">
                    <i className="ri-close-line"></i>
                  </button>
                </div>
              </div>

              {attrs.filter((a: VariationAttributeOption) => a.values?.length).map((attr: VariationAttributeOption) => (
                <VariantAttributeSelector
                  key={attr.attribute_slug}
                  attribute={attr}
                  selectedValueSlug={matchingVariantForSelection ? (matchingVariantForSelection.variation_attributes?.find((a: SelectedVariationItem) => a.attribute_slug === attr.attribute_slug)?.value_slug ?? null) : null}
                  isValueAvailable={(valueSlug) => isValueAvailableForAttribute(attr.attribute_slug, valueSlug)}
                  onSelect={(valueSlug, isDisabledClick) => handleSelectAttribute(attr.attribute_slug, valueSlug, isDisabledClick)}
                  isDisabled={isUpdating || isLoading}
                />
              ))}

              {!selectionUnchanged && !matchingVariantForSelection && Object.keys(selection).some((k) => selection[k]) && (
                <p className="text-sm text-amber-600">Выберите доступную комбинацию параметров</p>
              )}
              {applyError && <p className="text-sm text-red-600">{applyError}</p>}
              <div className="flex justify-end pt-2">
                <button
                  onClick={handleApply}
                  disabled={isUpdating || isLoading || !canApply}
                  className="bg-red-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed whitespace-nowrap"
                >
                  Применить
                </button>
              </div>
            </div>
          ) : (
            <div className="text-sm text-red-500">Не удалось загрузить варианты</div>
          )}
        </div>
      )}
    </div>
  );
}
