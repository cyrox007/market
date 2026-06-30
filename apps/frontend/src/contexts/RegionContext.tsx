import {
  createContext,
  useState,
  useEffect,
  useCallback,
  useContext,
} from 'react';
import { api } from '@/lib/api';
import type { ShippingLocation } from '@/lib/api';
import type { ReactNode } from 'react';

const REGION_STORAGE_KEY = 'selected_region_id';
const REGION_DATA_KEY = 'selected_region_data';
const REGION_DETECTED_KEY = 'region_auto_detected';

interface RegionContextType {
  region: ShippingLocation | null;
  regions: ShippingLocation[];
  loading: boolean;
  selectRegion: (selectedRegion: ShippingLocation | null) => void;
  reloadRegions: () => Promise<void>;
  getRegionId: () => number | null;
}

const RegionContext = createContext<RegionContextType | undefined>(undefined);

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
  const [regions, setRegions] = useState<ShippingLocation[]>([]);
  const [loading, setLoading] = useState<boolean>(() => !initialRegion);

  const loadRegions = useCallback(async () => {
    try {
      setLoading(true);
      const response = await api.regions.list();
      setRegions(response.data || []);
    } catch {
      setRegions([]);
    } finally {
      setLoading(false);
    }
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
          })
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

  const detectRegion = useCallback(async (city?: string): Promise<ShippingLocation | null> => {
    try {
      if (city && regions.length > 0) {
        const cityLower = city.toLowerCase().trim();
        const foundRegion = regions.find((r) => {
          const nameLower = r.name.toLowerCase();
          const cleanName = nameLower.replace(/^(г\.|г\.\s|с\.|пос\.|пгт\.|ст\.)\s*/i, '');
          const cleanCity = cityLower.replace(/^(г\.|г\.\s|с\.|пос\.|пгт\.|ст\.)\s*/i, '');
          return (
            cleanName === cleanCity ||
            cleanName.includes(cleanCity) ||
            cleanCity.includes(cleanName) ||
            nameLower.includes(cityLower) ||
            cityLower.includes(nameLower)
          );
        });
        if (foundRegion) {
          localStorage.setItem(REGION_DETECTED_KEY, 'true');
          return foundRegion;
        }
      }
      const detected = await api.regions.detect({ city });
      if (detected.region) {
        const foundInList = regions.find((r) => r.id === detected.region!.id);
        if (foundInList) {
          localStorage.setItem(REGION_DETECTED_KEY, 'true');
          return foundInList;
        }
        localStorage.setItem(REGION_DETECTED_KEY, 'true');
        return detected.region;
      }
    } catch {
      // ignore
    }
    return null;
  }, [regions]);

  const loadSavedRegion = useCallback((): boolean => {
    try {
      const savedRegionId = localStorage.getItem(REGION_STORAGE_KEY);
      if (savedRegionId && regions.length > 0) {
        const regionId = parseInt(savedRegionId, 10);
        const foundRegion = regions.find((r) => r.id === regionId);
        if (foundRegion) {
          setRegion(foundRegion);
          try {
            sessionStorage.setItem('current_region_id', foundRegion.id.toString());
          } catch {
            // ignore
          }
          return true;
        }
      }
    } catch {
      // ignore
    }
    return false;
  }, [regions]);

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
    [saveRegion]
  );

  useEffect(() => {
    loadRegions();
  }, [loadRegions]);

  useEffect(() => {
    const storedRegion = getStoredRegionData();
    if (storedRegion?.id && region?.id !== storedRegion.id) {
      setRegion(storedRegion);
      try {
        sessionStorage.setItem('current_region_id', storedRegion.id.toString());
        sessionStorage.setItem('current_region_data', JSON.stringify(storedRegion));
      } catch {
        // ignore
      }
    }

    if (regions.length > 0) {
      // Явно выбранный пользователем регион из localStorage имеет приоритет над SSR initialRegion.
      const hasSavedRegion = loadSavedRegion();
      if (!hasSavedRegion && !region) {
        detectRegion().then((detectedRegion) => {
          if (detectedRegion) {
            const foundRegion = regions.find((r) => r.id === detectedRegion.id);
            setRegion(foundRegion || detectedRegion);
            try {
              localStorage.setItem(REGION_DETECTED_KEY, 'true');
            } catch {
              // ignore
            }
          }
        });
      }
    }
  }, [regions, loadSavedRegion, detectRegion, region]);

  const getRegionId = useCallback((): number | null => region?.id ?? null, [region]);

  const value: RegionContextType = {
    region,
    regions,
    loading,
    selectRegion,
    reloadRegions: loadRegions,
    getRegionId,
  };

  return <RegionContext.Provider value={value}>{children}</RegionContext.Provider>;
}

export function useRegionContext(): RegionContextType {
  const ctx = useContext(RegionContext);
  if (ctx === undefined) {
    throw new Error('useRegionContext must be used within RegionProvider');
  }
  return ctx;
}
