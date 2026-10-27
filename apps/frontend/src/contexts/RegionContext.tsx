import { useState, useEffect, useCallback } from 'react';
import type { ShippingLocation, CustomerLocality } from '@/lib/api';
import type { ReactNode } from 'react';
import { RegionContext, type RegionContextType } from './region-context';

const REGION_STORAGE_KEY = 'selected_region_id';
const REGION_DATA_KEY = 'selected_region_data';
const REGION_DETECTED_KEY = 'region_auto_detected';

interface RegionProviderProps {
  children: ReactNode;
  /** Регион с SSR — чтобы сервер и клиент при гидрации рендерили одно и то же (избегаем hydration mismatch). */
  initialRegion?: ShippingLocation | null;
}

function getStoredRegionData(): ShippingLocation | null {
  if (typeof window === 'undefined') return null;
  try {
    const savedRegionData = localStorage.getItem(REGION_DATA_KEY);
    if (!savedRegionData) return null;
    const parsedRegion = JSON.parse(savedRegionData) as ShippingLocation;
    return parsedRegion?.id ? parsedRegion : null;
  } catch {
    return null;
  }
}

export function RegionProvider({ children, initialRegion = null }: RegionProviderProps) {
  // Первый рендер = initialRegion с SSR, без localStorage (иначе hydration mismatch и белый экран).
  const [region, setRegion] = useState<ShippingLocation | null>(initialRegion ?? null);
  const [locality, setLocality] = useState<CustomerLocality | null>(null);
  const [localityRestored, setLocalityRestored] = useState(false);
  useEffect(() => {
    try {
      const raw = localStorage.getItem('customer_locality');
      if (raw) {
        const saved = JSON.parse(raw);
        if (saved.locality?.externalId && saved.locality?.name) {
          setLocality(saved.locality);
          setRegion(saved.shippingLocation ?? null);
        }
      }
    } catch { /* Storage can be unavailable. */ }
    setLocalityRestored(true);
  }, []);
  const saveRegion = useCallback((selectedRegion: ShippingLocation | null) => {
    try {
      if (selectedRegion) {
        localStorage.setItem(REGION_STORAGE_KEY, selectedRegion.id.toString());
        localStorage.setItem(
          REGION_DATA_KEY,
          JSON.stringify({
            id: selectedRegion.id,
            name: selectedRegion.name,
            slug: selectedRegion.slug,
            type: selectedRegion.type,
            parent_id: selectedRegion.parent_id,
          }),
        );
        localStorage.removeItem(REGION_DETECTED_KEY);
      } else {
        localStorage.removeItem(REGION_STORAGE_KEY);
        localStorage.removeItem(REGION_DATA_KEY);
        localStorage.removeItem(REGION_DETECTED_KEY);
      }
    } catch {
      // ignore
    }
  }, []);

  const selectRegion = useCallback(
    (selectedRegion: ShippingLocation | null) => {
      if (!selectedRegion) return;
      setRegion(selectedRegion);
      saveRegion(selectedRegion);
      try {
        sessionStorage.setItem('current_region_id', selectedRegion.id.toString());
        sessionStorage.setItem('current_region_data', JSON.stringify(selectedRegion));
      } catch {
        // ignore
      }
      window.dispatchEvent(new CustomEvent('region-changed', { detail: selectedRegion }));
    },
    [saveRegion],
  );

  useEffect(() => {
    const storedRegion = getStoredRegionData();
    if (!localityRestored || locality) return;
    if (storedRegion?.id && region?.id !== storedRegion.id) {
      setRegion(storedRegion);
      try {
        sessionStorage.setItem('current_region_id', storedRegion.id.toString());
        sessionStorage.setItem('current_region_data', JSON.stringify(storedRegion));
      } catch {
        // ignore
      }
    }
  }, [region, locality, localityRestored]);

  const getRegionId = useCallback((): number | null => region?.id ?? null, [region]);
  const selectLocality = useCallback((selected: CustomerLocality, shippingLocation: ShippingLocation | null) => {
    setLocality(selected);
    setRegion(shippingLocation);
    saveRegion(shippingLocation);
    try {
      localStorage.setItem('customer_locality', JSON.stringify({ locality: selected, shippingLocation }));
      sessionStorage.removeItem('current_region_id');
      sessionStorage.removeItem('current_region_data');
      if (shippingLocation) {
        sessionStorage.setItem('current_region_id', String(shippingLocation.id));
        sessionStorage.setItem('current_region_data', JSON.stringify(shippingLocation));
      }
    } catch { /* Keep in-memory choice. */ }
    window.dispatchEvent(new CustomEvent('locality-changed', { detail: selected }));
    window.dispatchEvent(new CustomEvent('region-changed', { detail: shippingLocation }));
  }, [saveRegion]);

  const value: RegionContextType = {
    locality, locationReady: localityRestored, selectLocality, region, selectRegion, getRegionId,
  };
  return <RegionContext.Provider value={value}>{children}</RegionContext.Provider>;
}
