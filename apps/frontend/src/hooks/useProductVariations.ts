import { useState, useCallback } from 'react';
import { api } from '../lib/api';
import type { ProductDetail } from '../lib/api';

export interface ProductVariationData {
  product: ProductDetail;
}

export function useProductVariations() {
  const [variations, setVariations] = useState<Record<number, ProductVariationData>>({});
  const [loading, setLoading] = useState<Record<number, boolean>>({});

  const loadVariations = useCallback(async (slug: string, itemId: number, regionId?: number) => {
    if (loading[itemId]) return;

    setLoading(prev => ({ ...prev, [itemId]: true }));
    try {
      const data = await api.products.get(slug, { region_id: regionId });
      if (data.product) {
        setVariations(prev => ({
          ...prev,
          [itemId]: { product: data.product },
        }));
      }
    } catch (err) {
      console.error('Failed to load product variations:', err);
      throw err;
    } finally {
      setLoading(prev => ({ ...prev, [itemId]: false }));
    }
  }, [loading]);

  const clearVariations = useCallback(() => {
    setVariations({});
    setLoading({});
  }, []);

  return {
    variations,
    loading,
    loadVariations,
    clearVariations,
  };
}
