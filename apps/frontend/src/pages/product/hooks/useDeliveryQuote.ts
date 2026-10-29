import useSWR from 'swr';
import { useRegion } from '../../../hooks/useRegion';
import { api } from '../../../lib/api';
import type { ProductDetail, ProductVariant, ShippingQuote } from '../../../lib/api';
import { bundleQuoteRequest } from '../lib/bundle';
import { deliverySummary } from '../lib/delivery';
import { isVariableParent } from '../lib/variants';

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
  const isParent = isVariableParent(product);
  // У вариативного родителя бэк требует вариант: сам дешёвый не подставит
  const variantId = isParent ? selectedVariant?.id : undefined;
  const qty = Math.max(1, quantity);
  // Виртуальный SKU комплекта своего остатка не имеет — считаем по частям; без состава не считаем
  const kit = bundleQuoteRequest(product, selectedVariant, qty);
  const ready = product.is_sellable_bundle ? Boolean(kit) : !isParent || Boolean(variantId);

  const key =
    externalId && ready
      ? ([
          'shipping-quote',
          externalId,
          regionId,
          product.id,
          kit?.primary_sku_id ?? variantId,
          qty,
        ] as const)
      : null;

  const { data } = useSWR(
    key,
    () =>
      api.shipping.quote<ShippingQuote>({
        directory_external_id: externalId!,
        region_id: regionId,
        ...(kit ?? {
          items: [
            variantId
              ? { product_id: product.id, variant_id: variantId, quantity: qty }
              : { product_id: product.id, quantity: qty },
          ],
        }),
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
