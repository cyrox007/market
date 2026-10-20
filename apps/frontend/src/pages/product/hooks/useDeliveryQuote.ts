import useSWR from 'swr';
import { useRegion } from '../../../hooks/useRegion';
import { api } from '../../../lib/api';
import type { ProductDetail, ProductVariant } from '../../../lib/api';
import { deliverySummary } from '../lib/delivery';

/**
 * Сроки доставки и самовывоза для выбранного варианта, количества и населённого пункта.
 * Нет пункта или расчёт не пришёл — строк нет, без ошибки (market-docs/57, задача 05).
 */
export function useDeliveryQuote(
  product: ProductDetail,
  selectedVariant: ProductVariant | null,
  quantity: number,
) {
  const { locality, getRegionId } = useRegion();
  const externalId = locality?.externalId ?? null;
  const regionId = getRegionId() ?? undefined;
  const isVariableParent = Boolean(product.is_variable && !product.is_variant);
  // У вариативного родителя бэк требует вариант: сам дешёвый не подставит
  const variantId = isVariableParent ? selectedVariant?.id : undefined;
  const qty = Math.max(1, quantity);

  const key =
    externalId && (!isVariableParent || variantId)
      ? (['shipping-quote', externalId, regionId, product.id, variantId, qty] as const)
      : null;

  const { data } = useSWR(
    key,
    () =>
      api.shipping.quote({
        directory_external_id: externalId!,
        region_id: regionId,
        items: [
          variantId
            ? { product_id: product.id, variant_id: variantId, quantity: qty }
            : { product_id: product.id, quantity: qty },
        ],
      }),
    {
      revalidateOnFocus: false,
      shouldRetryOnError: false,
      dedupingInterval: 60_000,
      onError: (err) => console.warn('Shipping quote failed:', err),
    },
  );

  return deliverySummary(key ? data : undefined);
}
