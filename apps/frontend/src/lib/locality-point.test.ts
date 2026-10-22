import { describe, expect, it } from 'vitest';
import { hasSettlementPoint } from './locality-point';

const lipetsk = { externalId: 'e', name: 'Липецк', label: 'обл. Липецкая, г. Липецк', source: 'gar' };

describe('точка пункта для «Сначала ближе»', () => {
  it('центр населённого пункта с координатами — есть', () => {
    expect(
      hasSettlementPoint({
        ...lipetsk,
        latitude: 52.6,
        longitude: 39.6,
        coordinatePrecision: 'settlement_centre',
      }),
    ).toBe(true);
  });

  it('без координат или с другой точностью — нет (бэк такую точку не берёт)', () => {
    expect(hasSettlementPoint(lipetsk)).toBe(false);
    expect(hasSettlementPoint({ ...lipetsk, latitude: 52.6, longitude: 39.6 })).toBe(false);
    expect(
      hasSettlementPoint({ ...lipetsk, latitude: null, longitude: null, coordinatePrecision: 'settlement_centre' }),
    ).toBe(false);
    expect(hasSettlementPoint(null)).toBe(false);
  });
});
