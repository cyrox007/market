import { createContext, useContext } from 'react';
import type { ShippingLocation, CustomerLocality } from '../lib/api';

export interface RegionContextType {
  locality: CustomerLocality | null;
  selectLocality: (locality: CustomerLocality, shippingLocation: ShippingLocation | null) => void;
  region: ShippingLocation | null;
  regions: ShippingLocation[];
  loading: boolean;
  selectRegion: (selectedRegion: ShippingLocation | null) => void;
  reloadRegions: () => Promise<void>;
  getRegionId: () => number | null;
}

export const RegionContext = createContext<RegionContextType | undefined>(undefined);

export function useRegionContext(): RegionContextType {
  const ctx = useContext(RegionContext);
  if (ctx === undefined) {
    throw new Error('useRegionContext must be used within RegionProvider');
  }
  return ctx;
}
