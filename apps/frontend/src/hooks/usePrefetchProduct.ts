import { useCallback } from 'react';
import { mutate } from 'swr';
import { api } from '../lib/api';
import { useLocationParams } from './useLocationParams';
import { getProductKey } from '../utils/ssr-to-swr';

/**
 * Предзагрузка карточки товара при наведении на ссылку в каталоге.
 * Один запрос: товар + набор + сопутствующие.
 */
export function usePrefetchProduct() {
  const { params } = useLocationParams();

  return useCallback(
    (slug: string) => {
      if (!slug) return;

      api.products
        .get(slug, params)
        .then((data) => mutate(getProductKey(slug, params), data, { revalidate: false }))
        .catch(() => {});
    },
    [params],
  );
}
