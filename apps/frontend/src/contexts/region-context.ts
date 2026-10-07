import { createContext, useContext } from 'react';
import type { ShippingLocation } from '../lib/api';

export interface RegionContextType {
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
