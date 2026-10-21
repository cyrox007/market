import { useMemo } from 'react';
import type { LocationParams } from '../lib/api';
import { useRegion } from './useRegion';

/** region_id и directory_external_id для карточки и главной; ready — сохранённый пункт уже прочитан */
export function useLocationParams(): { params: LocationParams; ready: boolean } {
  const { getRegionId, locality, locationReady } = useRegion();
  const regionId = getRegionId() ?? undefined;
  const localityId = locality?.externalId;
  const params = useMemo(
    () => ({
      ...(regionId ? { region_id: regionId } : {}),
      ...(localityId ? { directory_external_id: localityId } : {}),
    }),
    [regionId, localityId],
  );
  return { params, ready: locationReady };
}
