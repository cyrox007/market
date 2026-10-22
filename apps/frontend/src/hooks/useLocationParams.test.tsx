import { renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it } from 'vitest';
import { RegionContext, type RegionContextType } from '../contexts/region-context';
import { useLocationParams } from './useLocationParams';

const wrap = (value: Partial<RegionContextType>) =>
  function Wrapper({ children }: { children: ReactNode }) {
    return (
      <RegionContext.Provider value={{ getRegionId: () => null, locality: null, locationReady: true, ...value } as RegionContextType}>
        {children}
      </RegionContext.Provider>
    );
  };

describe('местоположение для запросов карточки и главной', () => {
  it('регион цен и пункт справочника', () => {
    const { result } = renderHook(useLocationParams, {
      wrapper: wrap({ getRegionId: () => 7, locality: { externalId: 'eacb5f15' } as RegionContextType['locality'] }),
    });
    expect(result.current).toEqual({ params: { region_id: 7, directory_external_id: 'eacb5f15' }, ready: true });
  });

  it('ничего не выбрано — пустые параметры; до чтения сохранённого выбора — не готово', () => {
    const { result } = renderHook(useLocationParams, { wrapper: wrap({ locationReady: false }) });
    expect(result.current).toEqual({ params: {}, ready: false });
  });
});
