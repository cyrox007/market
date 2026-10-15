import type { ProductListParams } from '../../../lib/api';

const stable = (value: unknown): unknown =>
  value && typeof value === 'object' && !Array.isArray(value)
    ? Object.fromEntries(
        Object.entries(value)
          .filter(([, v]) => v !== undefined)
          .sort(([a], [b]) => a.localeCompare(b))
          .map(([k, v]) => [k, stable(v)]),
      )
    : value;

/** Тот же запрос к API, независимо от порядка ключей */
export const sameParams = (a: ProductListParams, b: ProductListParams) =>
  JSON.stringify(stable(a)) === JSON.stringify(stable(b));
