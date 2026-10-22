import type { CustomerLocality } from './api';

/**
 * Есть ли у пункта точка, от которой бэк считает расстояние до складов: только центр населённого
 * пункта с координатами (`CatalogDestination`). Без неё «Сначала ближе» не сортирует (market-docs/44)
 */
export const hasSettlementPoint = (locality: CustomerLocality | null) =>
  locality?.coordinatePrecision === 'settlement_centre' &&
  typeof locality.latitude === 'number' &&
  typeof locality.longitude === 'number';
