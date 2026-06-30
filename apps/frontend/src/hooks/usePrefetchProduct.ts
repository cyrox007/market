import { useCallback } from 'react';
import { mutate } from 'swr';
import { api } from '../lib/api';
import { useRegion } from './useRegion';
import { getProductKey } from '../utils/ssr-to-swr';

/**
 * Предзагрузка карточки товара при наведении на ссылку в каталоге.
 * Один запрос: товар + набор + сопутствующие.
 */
export function usePrefetchProduct() {
  const { getRegionId } = useRegion();

  return useCallback(
    (slug: string) => {
      if (!slug) return;

      const regionId = getRegionId();
      const key = getProductKey(slug, { region_id: regionId ?? undefined });

      api.products
        .get(slug, { region_id: regionId ?? undefined })
        .then((data) => mutate(key, data, { revalidate: false }))
        .catch(() => {});
    },
    [getRegionId],
  );
}
